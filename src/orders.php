<?php
/** Orders: save a new order and load one back. Uses the `orders` and `order_items` tables. */

require_once __DIR__ . '/cart.php';
require_once __DIR__ . '/mail.php';

/** Thrown when an item sold out between "Add to bag" and "Place order". */
class OutOfStockException extends RuntimeException
{
}

class EmptyCartException extends RuntimeException
{
}

class CartChangedException extends RuntimeException
{
}

/**
 * Save an order and its items in one transaction.
 *
 * @param array $customer name, email, phone, line1, line2, city, postal, notes
 * @param array $lines    from cart_lines()
 * @param array $totals   from cart_totals()
 * @param string $method  'cod' or 'payhere'
 * @return array{id:int, number:string}
 */
function create_order(array $customer, array $lines, array $totals, string $method = 'cod'): array
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        // 1) Take the stock first. Each product row is locked while we check and reduce it, so two
        //    customers can never buy the last item at the same time. Locking in id order avoids deadlocks.
        $byProduct = $lines;
        usort($byProduct, static fn ($a, $b) => $a['product']['id'] <=> $b['product']['id']);

        $read   = $pdo->prepare('SELECT stock FROM products WHERE id = ? AND is_active = 1 FOR UPDATE');
        $update = $pdo->prepare('UPDATE products SET stock = ? WHERE id = ?');

        foreach ($byProduct as $line) {
            $productId = (int) $line['product']['id'];
            $read->execute([$productId]);
            $json  = $read->fetchColumn();
            $stock = $json !== false ? json_decode((string) $json, true) : null;
            $have  = is_array($stock) ? (int) ($stock[$line['color']][$line['size']] ?? 0) : 0;

            if ($have < $line['qty']) {
                throw new OutOfStockException($line['product']['name'] . ' (' . $line['color'] . ' / ' . $line['size'] . ')');
            }
            $stock[$line['color']][$line['size']] = $have - $line['qty'];
            $update->execute([json_encode($stock, JSON_UNESCAPED_UNICODE), $productId]);
        }

        // 2) The order itself
        $stmt = $pdo->prepare(
            'INSERT INTO orders
                (order_number, user_id, status, subtotal, discount_amount, shipping_fee, total_amount,
                 customer_name, customer_email, customer_phone,
                 ship_line1, ship_line2, ship_city, ship_postal_code, notes,
                 payment_method, payment_status)
             VALUES
                (:number, :user_id, "pending", :subtotal, 0, :shipping, :total,
                 :name, :email, :phone,
                 :line1, :line2, :city, :postal, :notes,
                 :method, "pending")'
        );
        $stmt->execute([
            ':number'   => 'TMP-' . bin2hex(random_bytes(8)),      // replaced below once the id is known
            ':user_id'  => $_SESSION['user_id'] ?? null,
            ':subtotal' => number_format($totals['subtotal'], 2, '.', ''),
            ':shipping' => number_format($totals['delivery'], 2, '.', ''),
            ':total'    => number_format($totals['total'], 2, '.', ''),
            ':name'     => $customer['name'],
            ':email'    => $customer['email'],
            ':phone'    => $customer['phone'],
            ':line1'    => $customer['line1'],
            ':line2'    => $customer['line2'] !== '' ? $customer['line2'] : null,
            ':city'     => $customer['city'],
            ':postal'   => $customer['postal'],
            ':notes'    => $customer['notes'] !== '' ? $customer['notes'] : null,
            ':method'   => $method,
        ]);

        $id     = (int) $pdo->lastInsertId();
        $number = 'CB-' . (1000 + $id);                            // CB-1001, CB-1002 ...
        $pdo->prepare('UPDATE orders SET order_number = ? WHERE id = ?')->execute([$number, $id]);
        $pdo->prepare('INSERT INTO order_status_history (order_id, status, note) VALUES (?, "pending", ?)')
            ->execute([$id, $method === 'payhere' ? 'Order created; waiting for the PayHere payment.' : 'Order received; awaiting store confirmation.']);

        $item = $pdo->prepare(
            'INSERT INTO order_items
                (order_id, product_id, product_name, sku, size, color, unit_price, quantity, line_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($lines as $line) {
            $item->execute([
                $id,
                (int) $line['product']['id'],
                $line['product']['name'],
                $line['product']['sku'] . '-' . $line['color'] . '-' . $line['size'],
                $line['size'],
                $line['color'],
                number_format($line['unit_price'], 2, '.', ''),
                $line['qty'],
                number_format($line['line_total'], 2, '.', ''),
            ]);
        }

        $pdo->commit();
        return ['id' => $id, 'number' => $number];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** One order with its items, or null. */
function find_order(int $id): ?array
{
    $pdo  = db();
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
    $stmt->execute([$id]);
    $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $order;
}

/** One order by its number (CB-1001) with items, or null. */
function find_order_by_number(string $number): ?array
{
    $stmt = db()->prepare('SELECT id FROM orders WHERE order_number = ?');
    $stmt->execute([$number]);
    $id = $stmt->fetchColumn();

    return $id ? find_order((int) $id) : null;
}

/** A customer's orders (newest first), each with its items. */
function orders_for_user(int $userId, int $limit = 50): array
{
    $pdo  = db();
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int) $limit);
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$orders) {
        return [];
    }

    $ids  = array_column($orders, 'id');
    $in   = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id IN ($in) ORDER BY id");
    $stmt->execute($ids);

    $byOrder = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $byOrder[$item['order_id']][] = $item;
    }
    foreach ($orders as &$order) {
        $order['items'] = $byOrder[$order['id']] ?? [];
    }
    return $orders;
}

/** Label + CSS class for an order status. */
function order_status_badge(string $status): array
{
    $active = ['confirmed', 'processing', 'shipped', 'delivered'];
    return [
        ucfirst($status),
        $status === 'pending' ? 'status-badge--pending' : (in_array($status, $active, true) ? 'status-badge--active' : ''),
    ];
}


/** Status history of an order, newest first. */
function order_history(int $orderId): array
{
    $stmt = db()->prepare(
        'SELECT h.status, h.note, h.created_at, u.first_name AS changed_by_name
         FROM order_status_history h LEFT JOIN users u ON u.id = h.changed_by
         WHERE h.order_id = ? ORDER BY h.id DESC'
    );
    $stmt->execute([$orderId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Turn the visitor's bag into an order: check it, save it (taking the stock), empty the bag and send the e-mails.
 * Used by the checkout page and by POST /api/checkout.
 *
 * @throws EmptyCartException, CartChangedException, OutOfStockException
 * @return array{id:int, number:string}
 */
function place_order_from_cart(array $customer, string $method = 'cod'): array
{
    order_release_pending_payhere();                           // an unfinished online payment must not keep stock locked

    $lines = cart_lines();
    if (!$lines) {
        throw new EmptyCartException('Your bag is empty.');
    }
    $totals = cart_totals($lines);
    if (!$totals['all_ok']) {
        throw new CartChangedException('Some items are no longer available in the quantity you chose.');
    }

    $order = create_order($customer, $lines, $totals, $method);   // takes the stock; may throw OutOfStockException

    if ($method === 'payhere') {
        // Online payment: the bag stays full and no e-mail is sent until PayHere confirms the payment (payhere-notify.php)
        $_SESSION['payhere_order_id'] = $order['id'];
        return $order;
    }

    cart_clear();
    $_SESSION['last_order_id'] = $order['id'];

    if (MAIL_ENABLED) {                                         // the order is saved: a mail problem must never undo it
        try {
            $saved = find_order($order['id']);
            if ($saved) {
                send_order_confirmation($saved);
                send_admin_order_alert($saved);
            }
        } catch (Throwable $e) {
            error_log('Order e-mail failed: ' . $e->getMessage());
        }
    }
    return $order;
}


/* ---------- Online payment (PayHere) ---------- */

/**
 * Cancel an order whose online payment did not happen and put its items back in stock.
 * Only touches an order that is still pending and unpaid; returns true when it did.
 */
function order_release_unpaid(int $orderId, string $paymentStatus, string $note): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT status, payment_status, payment_method FROM orders WHERE id = ? FOR UPDATE');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order || $order['payment_method'] !== 'payhere' || $order['status'] !== 'pending' || $order['payment_status'] !== 'pending') {
            $pdo->rollBack();
            return false;
        }

        $items = $pdo->prepare('SELECT product_id, color, size, quantity FROM order_items WHERE order_id = ? AND product_id IS NOT NULL');
        $items->execute([$orderId]);
        $read   = $pdo->prepare('SELECT stock FROM products WHERE id = ? FOR UPDATE');
        $update = $pdo->prepare('UPDATE products SET stock = ? WHERE id = ?');
        foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $read->execute([$item['product_id']]);
            $json = $read->fetchColumn();
            if ($json === false) {
                continue;
            }
            $stock = json_decode((string) $json, true) ?: [];
            $stock[$item['color']][$item['size']] = (int) ($stock[$item['color']][$item['size']] ?? 0) + (int) $item['quantity'];
            $update->execute([json_encode($stock, JSON_UNESCAPED_UNICODE), $item['product_id']]);
        }

        $pdo->prepare("UPDATE orders SET status = 'cancelled', payment_status = ? WHERE id = ?")->execute([$paymentStatus, $orderId]);
        $pdo->prepare('INSERT INTO order_status_history (order_id, status, note) VALUES (?, "cancelled", ?)')->execute([$orderId, $note]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Release the visitor's unfinished PayHere order (if any), e.g. when they come back to checkout without paying. */
function order_release_pending_payhere(): void
{
    $id = (int) ($_SESSION['payhere_order_id'] ?? 0);
    if ($id > 0) {
        unset($_SESSION['payhere_order_id']);
        try {
            order_release_unpaid($id, 'failed', 'Payment not completed; items returned to stock.');
        } catch (Throwable $e) {
            error_log('Release of unpaid order failed: ' . $e->getMessage());
        }
    }
}

/**
 * PayHere confirmed the payment: the order becomes Paid / Processing. Safe to call twice (PayHere may repeat a callback).
 * @return bool true when this call recorded the payment
 */
function order_mark_paid(int $orderId, string $reference): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT status, payment_status FROM orders WHERE id = ? FOR UPDATE');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order || $order['payment_status'] === 'paid') {
            $pdo->rollBack();
            return false;
        }

        $late = $order['status'] !== 'pending';                       // e.g. the order was cancelled before the payment arrived
        $pdo->prepare(
            "UPDATE orders SET payment_status = 'paid', paid_at = NOW(), transaction_ref = ?, status = IF(status = 'pending', 'processing', status) WHERE id = ?"
        )->execute([$reference !== '' ? $reference : null, $orderId]);
        $pdo->prepare('INSERT INTO order_status_history (order_id, status, note) VALUES (?, ?, ?)')->execute([
            $orderId,
            $late ? $order['status'] : 'processing',
            $late ? 'Payment received after this order was ' . $order['status'] . '. Please review and refund if needed.' : 'Payment received via PayHere.',
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    if (MAIL_ENABLED && !$late) {
        try {
            $saved = find_order($orderId);
            if ($saved) {
                send_order_confirmation($saved);
                send_admin_order_alert($saved);
            }
        } catch (Throwable $e) {
            error_log('Order e-mail failed: ' . $e->getMessage());
        }
    }
    return true;
}

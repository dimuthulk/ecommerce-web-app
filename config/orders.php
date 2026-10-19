<?php
/** Orders: save a new order and load one back. Uses the `orders` and `order_items` tables. */

/** Thrown when an item sold out between "Add to bag" and "Place order". */
class OutOfStockException extends RuntimeException
{
}

/**
 * Save an order and its items in one transaction.
 *
 * @param array $customer name, email, phone, line1, line2, city, postal, notes
 * @param array $lines    from cart_lines()
 * @param array $totals   from cart_totals()
 * @return array{id:int, number:string}
 */
function create_order(array $customer, array $lines, array $totals): array
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
                 "cod", "pending")'
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
        ]);

        $id     = (int) $pdo->lastInsertId();
        $number = 'CB-' . (1000 + $id);                            // CB-1001, CB-1002 ...
        $pdo->prepare('UPDATE orders SET order_number = ? WHERE id = ?')->execute([$number, $id]);

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

<?php
/** Orders: save a new order and load one back. Uses the `orders` and `order_items` tables. */

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

        // product_id stays NULL while products live in data/products.php (the products table is still empty).
        $item = $pdo->prepare(
            'INSERT INTO order_items
                (order_id, product_id, product_name, sku, size, color, unit_price, quantity, line_total)
             VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($lines as $line) {
            $item->execute([
                $id,
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

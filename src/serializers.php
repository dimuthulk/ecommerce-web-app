<?php
/** The shapes the API sends for users, orders and the bag (never password hashes or tokens). */

function user_public(array $u): array
{
    return [
        'id'         => (int) $u['id'],
        'name'       => user_display_name($u),
        'first_name' => $u['first_name'],
        'last_name'  => $u['last_name'],
        'email'      => $u['email'],
        'phone'      => $u['phone'],
        'role'       => $u['role'],
        'address'    => [
            'line1'  => $u['address_line1'],
            'city'   => $u['city'],
            'postal' => $u['postal_code'],
        ],
    ];
}

function order_public(array $o, bool $withHistory = true): array
{
    $out = [
        'number'     => $o['order_number'],
        'url'        => url('orders/' . $o['order_number']),
        'status'     => $o['status'],
        'placed_at'  => $o['created_at'],
        'totals'     => [
            'subtotal' => (float) $o['subtotal'],
            'delivery' => (float) $o['shipping_fee'],
            'total'    => (float) $o['total_amount'],
            'currency' => currency_code(),
        ],
        'payment'    => ['method' => $o['payment_method'], 'status' => $o['payment_status']],
        'customer'   => ['name' => $o['customer_name'], 'email' => $o['customer_email'], 'phone' => $o['customer_phone']],
        'address'    => [
            'line1'  => $o['ship_line1'],
            'line2'  => $o['ship_line2'],
            'city'   => $o['ship_city'],
            'postal' => $o['ship_postal_code'],
        ],
        'notes'      => $o['notes'],
        'items'      => array_map(static fn ($i) => [
            'product_id' => $i['product_id'] !== null ? (int) $i['product_id'] : null,
            'name'       => $i['product_name'],
            'sku'        => $i['sku'],
            'color'      => $i['color'],
            'size'       => $i['size'],
            'quantity'   => (int) $i['quantity'],
            'unit_price' => (float) $i['unit_price'],
            'line_total' => (float) $i['line_total'],
        ], $o['items'] ?? []),
    ];
    if ($withHistory) {
        $out['history'] = array_map(static fn ($h) => ['status' => $h['status'], 'note' => $h['note'], 'at' => $h['created_at']], order_history((int) $o['id']));
    }
    return $out;
}

/** The bag with its totals, as the API sends it. */
function cart_public(): array
{
    cart_prune();
    $lines  = cart_lines();
    $totals = cart_totals($lines);

    $items = array_map(static function (array $l): array {
        $p = $l['product'];
        return [
            'key'        => $l['key'],
            'product_id' => $p['id'],
            'name'       => $p['name'],
            'sku'        => $p['sku'],
            'url'        => product_url($p['slug']),
            'image'      => isset($p['images'][0]) ? url($p['images'][0]) : null,
            'color'      => $l['color'],
            'size'       => $l['size'],
            'quantity'   => $l['qty'],
            'available'  => $l['available'],
            'unit_price' => $l['unit_price'],
            'line_total' => $l['line_total'],
            'ok'         => $l['ok'],
        ];
    }, $lines);

    return ['items' => $items, 'totals' => $totals + ['currency' => currency_code()]];
}

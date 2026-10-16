<?php
/**
 * Cart helpers.
 * The cart lives in $_SESSION['cart'] as  [ "productId|color|size" => quantity ]
 * (see cart_key() in app.php). Prices and stock always come from the product data,
 * never from the browser.
 */

/** Cart lines with product, price and stock details. Unknown products are skipped. */
function cart_lines(): array
{
    $byId = [];
    foreach (all_products() as $p) {
        $byId[(int) $p['id']] = $p;
    }

    $lines = [];
    foreach ($_SESSION['cart'] ?? [] as $key => $qty) {
        [$id, $color, $size] = array_pad(explode('|', (string) $key, 3), 3, '');
        $product = $byId[(int) $id] ?? null;

        if ($product === null || !in_array($color, $product['colors'], true) || !isset($product['stock'][$color][$size])) {
            continue;
        }

        $qty       = max(1, (int) $qty);
        $available = (int) $product['stock'][$color][$size];
        $unit      = (float) ($product['sale_price'] ?: $product['price']);

        $lines[] = [
            'key'        => (string) $key,
            'product'    => $product,
            'color'      => $color,
            'size'       => $size,
            'qty'        => $qty,
            'available'  => $available,
            'unit_price' => $unit,
            'line_total' => $unit * $qty,
            'ok'         => $available >= 1 && $qty <= $available,   // enough stock for this quantity
        ];
    }
    return $lines;
}

/** Items, subtotal, delivery fee and total for a list of cart lines. */
function cart_totals(array $lines): array
{
    $items    = array_sum(array_column($lines, 'qty'));
    $subtotal = array_sum(array_column($lines, 'line_total'));
    $delivery = $items > 0 ? (float) DELIVERY_FEE : 0.0;

    return [
        'items'    => (int) $items,
        'subtotal' => (float) $subtotal,
        'delivery' => $delivery,
        'total'    => (float) $subtotal + $delivery,
        'all_ok'   => $lines !== [] && !in_array(false, array_column($lines, 'ok'), true),
    ];
}

/** Drop cart entries that no longer match a product (keeps the header count honest). */
function cart_prune(): void
{
    $valid = array_column(cart_lines(), 'key');
    foreach (array_keys($_SESSION['cart'] ?? []) as $key) {
        if (!in_array((string) $key, $valid, true)) {
            unset($_SESSION['cart'][$key]);
        }
    }
}

function cart_clear(): void
{
    unset($_SESSION['cart']);
}

/** "1 item" / "3 items" */
function items_label(int $count): string
{
    return $count . ' item' . ($count === 1 ? '' : 's');
}

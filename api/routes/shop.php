<?php
/** Shop endpoints: session, products, categories, bag, checkout, contact. */

/* ---------- Session ---------- */

// GET /api/session  -> who is signed in, the CSRF token for POST/PATCH/DELETE, bag size
api_route('GET', '/session', function () {
    $user = api_user();
    api_ok([
        'csrf'       => csrf_token(),
        'user'       => $user ? user_public($user) : null,
        'cart_count' => cart_count(),
        'store'      => ['name' => app_name(), 'currency' => currency_code(), 'delivery_fee' => delivery_fee()],
    ]);
});

/* ---------- Products ---------- */

// GET /api/products?q=&category[]=&size[]=&color[]=&stock=1&sort=recommended|price_asc|price_desc|name&page=&per_page=
api_route('GET', '/products', function () {
    $result = products_query($_GET);
    api_ok(
        array_map('product_public', $result['items']),
        200,
        ['page' => $result['page'], 'pages' => $result['pages'], 'per_page' => $result['per_page'], 'total' => $result['total']]
    );
});

// GET /api/products/{slug}
api_route('GET', '/products/{slug}', function (array $p) {
    $product = product_find_by_slug($p['slug']);
    if ($product === null) {
        api_fail(404, 'not_found', 'Product not found.');
    }
    api_ok(product_public($product));
});

// GET /api/categories
api_route('GET', '/categories', function () {
    api_ok(store_categories());
});

/* ---------- Bag ---------- */

// GET /api/cart
api_route('GET', '/cart', function () {
    api_ok(cart_public());
});

// POST /api/cart/items   { product_id | slug, color, size, qty }
api_route('POST', '/cart/items', function () {
    $in      = api_input();
    $product = isset($in['product_id']) ? product_find_by_id((int) $in['product_id']) : product_find_by_slug(api_str($in, 'slug'));
    if ($product === null) {
        api_fail(404, 'not_found', 'Product not found.');
    }

    $color = api_str($in, 'color');
    $size  = api_str($in, 'size');
    $qty   = max(1, (int) ($in['qty'] ?? 1));
    $fields = [];

    if (!in_array($color, $product['colors'], true)) {
        $fields['color'] = 'Please choose a color.';
    } elseif (!isset($product['stock'][$color][$size])) {
        $fields['size'] = 'Please choose a size.';
    } elseif ((int) $product['stock'][$color][$size] < 1) {
        $fields['size'] = 'This size is sold out in this color.';
    }
    if ($fields) {
        api_fail(422, 'validation_failed', 'Please choose an available size.', $fields);
    }

    $available = (int) $product['stock'][$color][$size];
    $key       = cart_key($product['id'], $color, $size);
    $before    = (int) ($_SESSION['cart'][$key] ?? 0);
    $after     = min($before + $qty, $available);
    $_SESSION['cart'][$key] = $after;

    $added  = $after - $before;
    $notice = $added < 1 ? 'at_maximum' : ($after < $before + $qty ? 'limited_by_stock' : 'added');

    api_ok(
        ['notice' => $notice, 'added' => $added, 'name' => $product['name'], 'label' => $color . ' / ' . $size, 'available' => $available, 'cart' => cart_public()],
        $added > 0 ? 201 : 200
    );
});

// PATCH /api/cart/items   { key, qty }
api_route('PATCH', '/cart/items', function () {
    $in  = api_input();
    $key = api_str($in, 'key');
    $qty = (int) ($in['qty'] ?? 0);

    $line = null;
    foreach (cart_lines() as $candidate) {
        if ($candidate['key'] === $key) { $line = $candidate; break; }
    }
    if ($line === null) {
        api_fail(404, 'not_found', 'That item is not in your bag.');
    }
    if ($qty < 1) {
        api_fail(422, 'validation_failed', 'Quantity must be at least 1. Use DELETE to remove an item.', ['qty' => 'Quantity must be at least 1.']);
    }
    if ($line['available'] < 1) {
        api_fail(409, 'sold_out', $line['color'] . ' / ' . $line['size'] . ' is sold out. Remove it to continue.');
    }

    $notice = 'updated';
    if ($qty > $line['available']) {
        $qty    = $line['available'];
        $notice = 'limited_by_stock';
    }
    $_SESSION['cart'][$key] = $qty;
    api_ok(['notice' => $notice, 'cart' => cart_public()]);
});

// DELETE /api/cart/items   { key }
api_route('DELETE', '/cart/items', function () {
    $key = api_str(api_input(), 'key');
    if ($key === '' || !isset($_SESSION['cart'][$key])) {
        api_fail(404, 'not_found', 'That item is not in your bag.');
    }
    unset($_SESSION['cart'][$key]);
    api_ok(['cart' => cart_public()]);
});

/* ---------- Checkout ---------- */

// POST /api/checkout   { name, email, phone, line1, line2?, city, postal, notes?, payment_method?, terms }
api_route('POST', '/checkout', function () {
    [$clean, $errors] = validate_checkout(api_input());
    if ($errors) {
        api_fail(422, 'validation_failed', 'Please check the highlighted fields.', $errors);
    }

    try {
        $order = place_order_from_cart($clean);
    } catch (EmptyCartException $e) {
        api_fail(409, 'cart_empty', $e->getMessage());
    } catch (CartChangedException $e) {
        api_fail(409, 'cart_changed', $e->getMessage());
    } catch (OutOfStockException $e) {
        api_fail(409, 'out_of_stock', $e->getMessage() . ' is no longer available in that quantity.');
    }

    api_ok(['number' => $order['number'], 'redirect' => url('order-confirmation.php')], 201);
});

/* ---------- Contact ---------- */

// POST /api/contact   { name, email, order_ref?, message }
api_route('POST', '/contact', function () {
    $in = api_input();
    [$clean, $errors] = validate_contact($in);
    if ($errors) {
        api_fail(422, 'validation_failed', 'Please check the highlighted fields.', $errors);
    }

    if (!empty($in['website'])) {                        // spam trap: bots fill this hidden field in
        api_ok(['sent' => true], 201);
    }

    $user   = api_user();
    $result = contact_save($clean, $user ? (int) $user['id'] : null);
    if ($result === 'limited') {
        api_fail(429, 'rate_limited', 'You have sent a few messages recently. Please try again a little later.');
    }
    api_ok(['sent' => true], 201);
});

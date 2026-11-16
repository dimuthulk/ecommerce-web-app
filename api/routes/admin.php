<?php
/** Admin endpoints (all need an administrator, except /admin/login). */

require_once __DIR__ . '/../../src/admin.php';

/* ---------- Sign in ---------- */

// POST /api/admin/login   { email, password }
api_route('POST', '/admin/login', function () {
    $in       = api_input();
    $email    = api_str($in, 'email');
    $password = is_string($in['password'] ?? null) ? $in['password'] : '';
    if ($email === '' || $password === '') {
        api_fail(422, 'validation_failed', 'Please enter your email and password.', ['email' => $email === '' ? 'Please enter your email.' : '', 'password' => $password === '' ? 'Please enter your password.' : '']);
    }

    $result = auth_attempt($email, $password);
    if ($result['error'] === 'locked') {
        api_fail(429, 'locked', 'Too many attempts. Please try again in ' . LOGIN_LOCK_MINUTES . ' minutes.');
    }
    // A customer account must not learn that these credentials are valid: same answer as a wrong password
    if ($result['user'] === null || $result['user']['role'] !== 'admin') {
        api_fail(401, 'invalid_credentials', 'The email or password is incorrect.');
    }

    login_user((int) $result['user']['id']);
    api_ok(['user' => user_public($result['user']), 'redirect' => url('admin/'), 'csrf' => csrf_token()]);
});

// GET /api/admin/me
api_route('GET', '/admin/me', function () {
    api_ok(user_public(api_require_admin()));
}, 'admin');

/* ---------- Dashboard ---------- */

// GET /api/admin/dashboard?period=this_month|last_30|last_month|all
api_route('GET', '/admin/dashboard', function () {
    $period = is_string($_GET['period'] ?? null) ? $_GET['period'] : 'this_month';
    [$from, $to, $label] = period_range($period);
    $pdo = db();

    [$cl, $cv] = date_clause('o.created_at', $from, $to);
    $stmt = $pdo->prepare('SELECT o.status, COUNT(*) FROM orders o ' . ($cl ? 'WHERE ' . implode(' AND ', $cl) : '') . ' GROUP BY o.status');
    $stmt->execute($cv);
    $byStatus = array_fill_keys(ORDER_STATUSES, 0);
    foreach ($stmt->fetchAll(PDO::FETCH_NUM) as [$status, $count]) {
        $byStatus[$status === 'processing' ? 'confirmed' : $status] = ($byStatus[$status === 'processing' ? 'confirmed' : $status] ?? 0) + (int) $count;
    }

    [$dl, $dv] = date_clause(DELIVERED_AT_SQL, $from, $to);
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(o.subtotal), 0), COUNT(*) FROM orders o WHERE o.status = 'delivered'" . ($dl ? ' AND ' . implode(' AND ', $dl) : ''));
    $stmt->execute($dv);
    [$sales, $delivered] = $stmt->fetch(PDO::FETCH_NUM);

    $variants  = admin_variants(all_products_admin());
    $low       = array_values(array_filter($variants, static fn ($v) => $v['status'] === 'low'));
    $out       = array_values(array_filter($variants, static fn ($v) => $v['status'] === 'out'));
    $attention = array_slice(array_merge($low, $out), 0, 6);

    $recent = $pdo->query('SELECT order_number, customer_name, created_at, total_amount, status FROM orders ORDER BY id DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);

    api_ok([
        'period'    => ['key' => $period, 'label' => $label, 'from' => $from, 'to' => $to],
        'orders'    => ['total' => array_sum($byStatus), 'by_status' => $byStatus],
        'sales'     => ['merchandise' => (float) $sales, 'delivered_orders' => (int) $delivered, 'currency' => currency_code()],
        'stock'     => ['low' => count($low), 'out' => count($out), 'limit' => LOW_STOCK_LIMIT, 'attention' => $attention],
        'recent'    => array_map(static fn ($r) => ['number' => $r['order_number'], 'customer' => $r['customer_name'], 'placed_at' => $r['created_at'], 'total' => (float) $r['total_amount'], 'status' => $r['status']], $recent),
    ]);
}, 'admin');

/* ---------- Products ---------- */

// GET /api/admin/products?q=&category=&page=
api_route('GET', '/admin/products', function () {
    $q   = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    $cat = is_string($_GET['category'] ?? null) ? trim($_GET['category']) : '';

    $products = array_values(array_filter(all_products_admin(), static function ($p) use ($q, $cat) {
        if ($cat !== '' && $p['category'] !== $cat) return false;
        return $q === '' || stripos($p['name'] . ' ' . $p['sku'], $q) !== false;
    }));

    $pg   = admin_pagination(count($products), 10);
    $page = array_slice($products, $pg['offset'], $pg['per_page']);
    $all  = all_products_admin();

    api_ok(
        array_map(static fn ($p) => ['id' => $p['id'], 'name' => $p['name'], 'sku' => $p['sku'], 'category' => $p['category'], 'price' => $p['price'], 'units' => product_units($p), 'published' => $p['is_active']], $page),
        200,
        ['page' => $pg['page'], 'pages' => $pg['pages'], 'per_page' => $pg['per_page'], 'total' => $pg['total'],
         'summary' => ['products' => count($all), 'categories' => count(array_unique(array_column($all, 'category'))), 'units' => array_sum(array_map('product_units', $all))]]
    );
}, 'admin');

// GET /api/admin/products/{id}
api_route('GET', '/admin/products/{id}', function (array $p) {
    $product = admin_find_product((int) $p['id']);
    if ($product === null) api_fail(404, 'not_found', 'Product not found.');
    api_ok(admin_product_view($product));
}, 'admin');

// POST /api/admin/products   { name, sku, category, price, description, published, image_alt?, variants: [{color,size,stock}] }
api_route('POST', '/admin/products', function () {
    [$clean, $errors] = admin_validate_product(api_input());
    if ($errors) api_fail(422, 'validation_failed', 'Please check the highlighted fields.', $errors);

    $id = admin_save_product(0, $clean);
    api_ok(admin_product_view(admin_find_product($id)), 201);
}, 'admin');

// PATCH /api/admin/products/{id}   (same fields)
api_route('PATCH', '/admin/products/{id}', function (array $p) {
    $id = (int) $p['id'];
    if (admin_find_product($id) === null) api_fail(404, 'not_found', 'Product not found.');

    [$clean, $errors] = admin_validate_product(api_input(), $id);
    if ($errors) api_fail(422, 'validation_failed', 'Please check the highlighted fields.', $errors);

    admin_save_product($id, $clean);
    api_ok(admin_product_view(admin_find_product($id)));
}, 'admin');

// POST /api/admin/products/{id}/images   (multipart: image)
api_route('POST', '/admin/products/{id}/images', function (array $p) {
    $product = admin_find_product((int) $p['id']);
    if ($product === null) api_fail(404, 'not_found', 'Product not found.');
    if (count($product['images']) >= MAX_IMAGES) api_fail(422, 'too_many_images', 'A product can have up to ' . MAX_IMAGES . ' pictures.');

    $path   = admin_store_image($_FILES['image'] ?? []);
    $images = array_merge($product['images'], [$path]);
    db()->prepare('UPDATE products SET images = ? WHERE id = ?')->execute([json_encode($images), $product['id']]);
    api_ok(admin_product_view(admin_find_product($product['id'])), 201);
}, 'admin');

// PATCH /api/admin/products/{id}/images   { order: [path, path, ...] }  (the first one is the main picture)
api_route('PATCH', '/admin/products/{id}/images', function (array $p) {
    $product = admin_find_product((int) $p['id']);
    if ($product === null) api_fail(404, 'not_found', 'Product not found.');

    $order = array_values(array_filter((array) (api_input()['order'] ?? []), 'is_string'));
    if (array_diff($order, $product['images']) || count($order) !== count($product['images'])) {
        api_fail(422, 'validation_failed', 'The list must contain exactly the current pictures.');
    }
    db()->prepare('UPDATE products SET images = ? WHERE id = ?')->execute([json_encode($order), $product['id']]);
    api_ok(admin_product_view(admin_find_product($product['id'])));
}, 'admin');

// DELETE /api/admin/products/{id}/images   { path }
api_route('DELETE', '/admin/products/{id}/images', function (array $p) {
    $product = admin_find_product((int) $p['id']);
    if ($product === null) api_fail(404, 'not_found', 'Product not found.');

    $path = api_str(api_input(), 'path');
    if (!in_array($path, $product['images'], true)) api_fail(404, 'not_found', 'That picture is not on this product.');

    $images = array_values(array_diff($product['images'], [$path]));
    db()->prepare('UPDATE products SET images = ? WHERE id = ?')->execute([json_encode($images), $product['id']]);
    if (strpos($path, 'assets/uploads/products/') === 0) {
        @unlink(__DIR__ . '/../../' . $path);
    }
    api_ok(admin_product_view(admin_find_product($product['id'])));
}, 'admin');

/* ---------- Variant stock ---------- */

// GET /api/admin/variants?q=&status=all|in|low|out&page=
api_route('GET', '/admin/variants', function () {
    $q      = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'all';

    $rows = array_values(array_filter(admin_variants(all_products_admin()), static function ($v) use ($q, $status) {
        if (in_array($status, ['in', 'low', 'out'], true) && $v['status'] !== $status) return false;
        return $q === '' || stripos($v['product'] . ' ' . $v['sku'] . ' ' . $v['color'] . ' ' . $v['size'], $q) !== false;
    }));

    $pg = admin_pagination(count($rows), 10);
    api_ok(array_slice($rows, $pg['offset'], $pg['per_page']), 200, ['page' => $pg['page'], 'pages' => $pg['pages'], 'per_page' => $pg['per_page'], 'total' => $pg['total'], 'low_limit' => LOW_STOCK_LIMIT]);
}, 'admin');

// PATCH /api/admin/variants   { product_id, color, size, stock }
api_route('PATCH', '/admin/variants', function () {
    $in    = api_input();
    $id    = (int) ($in['product_id'] ?? 0);
    $color = api_str($in, 'color');
    $size  = api_str($in, 'size');
    $qty   = $in['stock'] ?? '';

    if (!is_numeric($qty) || (int) $qty != $qty || (int) $qty < 0 || (int) $qty > 100000) {
        api_fail(422, 'validation_failed', 'Stock must be a whole number, zero or greater.', ['stock' => 'Stock must be a whole number, zero or greater.']);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT stock FROM products WHERE id = ? FOR UPDATE');      // locked: no overselling while we save
        $stmt->execute([$id]);
        $json  = $stmt->fetchColumn();
        $stock = $json !== false ? json_decode((string) $json, true) : null;
        if (!is_array($stock) || !isset($stock[$color][$size])) {
            $pdo->rollBack();
            api_fail(404, 'not_found', 'That variant does not exist.');
        }
        $stock[$color][$size] = (int) $qty;
        $pdo->prepare('UPDATE products SET stock = ? WHERE id = ?')->execute([json_encode($stock, JSON_UNESCAPED_UNICODE), $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $product = admin_find_product($id);
    $variant = array_values(array_filter(admin_variants([$product]), static fn ($v) => $v['color'] === $color && $v['size'] === $size))[0];
    $variant['product_total'] = array_sum(array_column(admin_variants([$product]), 'available'));
    api_ok($variant);
}, 'admin');

/* ---------- Categories ---------- */

function admin_categories(string $q = ''): array
{
    $sql = 'SELECT c.id, c.name, c.slug, c.description, c.is_visible, c.sort_order,
                   (SELECT COUNT(*) FROM products p WHERE p.category = c.name) AS products
            FROM categories c';
    $params = [];
    if ($q !== '') { $sql .= ' WHERE c.name LIKE ? OR c.slug LIKE ?'; $params = [like_term($q), like_term($q)]; }
    $stmt = db()->prepare($sql . ' ORDER BY c.sort_order, c.name');
    $stmt->execute($params);
    return array_map(static fn ($r) => ['id' => (int) $r['id'], 'name' => $r['name'], 'slug' => $r['slug'], 'description' => (string) $r['description'], 'visible' => (bool) $r['is_visible'], 'products' => (int) $r['products']], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function admin_validate_category(array $in, int $exceptId = 0): array
{
    $c = ['name' => api_str($in, 'name'), 'slug' => strtolower(api_str($in, 'slug')), 'description' => api_str($in, 'description'), 'visible' => !empty($in['visible']) ? 1 : 0];
    $e = [];
    if ($c['name'] === '' || v_length($c['name']) > 80) $e['name'] = 'Please enter the category name (up to 80 characters).';
    if ($c['slug'] === '') $c['slug'] = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($c['name'])), '-');
    if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $c['slug']) || strlen($c['slug']) > 100) $e['slug'] = 'Use lowercase letters, numbers and hyphens only.';
    if (v_length($c['description']) > 1000) $e['description'] = 'The description is too long.';

    foreach (['name' => 'name', 'slug' => 'slug'] as $field => $column) {
        if (isset($e[$field])) continue;
        $stmt = db()->prepare("SELECT COUNT(*) FROM categories WHERE $column = ? AND id <> ?");
        $stmt->execute([$c[$field], $exceptId]);
        if ((int) $stmt->fetchColumn() > 0) $e[$field] = 'Another category already uses this ' . $field . '.';
    }
    return [$c, $e];
}

// GET /api/admin/categories?q=
api_route('GET', '/admin/categories', function () {
    $list = admin_categories(is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '');
    api_ok($list, 200, ['total' => count($list)]);
}, 'admin');

// POST /api/admin/categories   { name, slug?, description?, visible }
api_route('POST', '/admin/categories', function () {
    [$c, $errors] = admin_validate_category(api_input());
    if ($errors) api_fail(422, 'validation_failed', 'Please check the highlighted fields.', $errors);

    db()->prepare('INSERT INTO categories (name, slug, description, is_visible) VALUES (?, ?, ?, ?)')
        ->execute([$c['name'], $c['slug'], $c['description'] ?: null, $c['visible']]);
    api_ok(['id' => (int) db()->lastInsertId()], 201);
}, 'admin');

// PATCH /api/admin/categories/{id}   (renaming also renames the category on its products)
api_route('PATCH', '/admin/categories/{id}', function (array $p) {
    $id  = (int) $p['id'];
    $old = db()->prepare('SELECT name FROM categories WHERE id = ?');
    $old->execute([$id]);
    $oldName = $old->fetchColumn();
    if ($oldName === false) api_fail(404, 'not_found', 'Category not found.');

    [$c, $errors] = admin_validate_category(api_input(), $id);
    if ($errors) api_fail(422, 'validation_failed', 'Please check the highlighted fields.', $errors);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE categories SET name = ?, slug = ?, description = ?, is_visible = ? WHERE id = ?')
            ->execute([$c['name'], $c['slug'], $c['description'] ?: null, $c['visible'], $id]);
        if ($c['name'] !== $oldName) {
            $pdo->prepare('UPDATE products SET category = ? WHERE category = ?')->execute([$c['name'], $oldName]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    api_ok(['id' => $id]);
}, 'admin');

// DELETE /api/admin/categories/{id}   (only when no product uses it)
api_route('DELETE', '/admin/categories/{id}', function (array $p) {
    $id   = (int) $p['id'];
    $stmt = db()->prepare('SELECT name FROM categories WHERE id = ?');
    $stmt->execute([$id]);
    $name = $stmt->fetchColumn();
    if ($name === false) api_fail(404, 'not_found', 'Category not found.');

    $stmt = db()->prepare('SELECT COUNT(*) FROM products WHERE category = ?');
    $stmt->execute([$name]);
    if ((int) $stmt->fetchColumn() > 0) api_fail(409, 'category_in_use', 'Reassign its products before removing this category.');

    db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
    api_ok(['deleted' => true]);
}, 'admin');

/* ---------- Orders ---------- */

/** WHERE parts shared by the orders list and the orders report. */
function admin_order_filter(bool $withStatus = true): array
{
    $sql = [];
    $val = [];
    $q   = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    if ($q !== '') {
        $sql[] = '(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ?)';
        array_push($val, like_term($q), like_term($q), like_term($q));
    }
    $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
    if ($withStatus && in_array($status, ORDER_STATUSES, true)) {
        $sql[] = 'o.status = ?';
        $val[] = $status;
    }
    [$dl, $dv] = date_clause('o.created_at', valid_date($_GET['from'] ?? null), valid_date($_GET['to'] ?? null));
    return [array_merge($sql, $dl), array_merge($val, $dv)];
}

function admin_order_row(array $r): array
{
    return ['number' => $r['order_number'], 'customer' => $r['customer_name'], 'email' => $r['customer_email'], 'placed_at' => $r['created_at'], 'total' => (float) $r['total_amount'], 'status' => $r['status']];
}

// GET /api/admin/orders?q=&status=&from=&to=&page=
api_route('GET', '/admin/orders', function () {
    $pdo = db();
    [$sql, $val] = admin_order_filter();
    $where = $sql ? 'WHERE ' . implode(' AND ', $sql) : '';

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders o $where");
    $stmt->execute($val);
    $pg = admin_pagination((int) $stmt->fetchColumn(), 10);

    $stmt = $pdo->prepare("SELECT o.* FROM orders o $where ORDER BY o.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
    $stmt->execute($val);
    $rows = array_map('admin_order_row', $stmt->fetchAll(PDO::FETCH_ASSOC));

    // numbers for the status tabs (same search + dates, any status)
    [$sql2, $val2] = admin_order_filter(false);
    $stmt = $pdo->prepare('SELECT o.status, COUNT(*) FROM orders o ' . ($sql2 ? 'WHERE ' . implode(' AND ', $sql2) : '') . ' GROUP BY o.status');
    $stmt->execute($val2);
    $counts = array_fill_keys(ORDER_STATUSES, 0);
    foreach ($stmt->fetchAll(PDO::FETCH_NUM) as [$s, $n]) {
        $counts[$s === 'processing' ? 'confirmed' : $s] = ($counts[$s === 'processing' ? 'confirmed' : $s] ?? 0) + (int) $n;
    }

    api_ok($rows, 200, ['page' => $pg['page'], 'pages' => $pg['pages'], 'per_page' => $pg['per_page'], 'total' => $pg['total'], 'counts' => $counts + ['all' => array_sum($counts)]]);
}, 'admin');

// GET /api/admin/orders/{number}
api_route('GET', '/admin/orders/{number}', function (array $p) {
    $order = find_order_by_number($p['number']);
    if ($order === null) api_fail(404, 'not_found', 'Order not found.');

    $data = order_public($order);
    $data['customer']['user_id'] = $order['user_id'] !== null ? (int) $order['user_id'] : null;
    api_ok($data);
}, 'admin');

// PATCH /api/admin/orders/{number}/status   { status, note? }
api_route('PATCH', '/admin/orders/{number}/status', function (array $p) {
    $in    = api_input();
    $admin = api_require_admin();
    $order = order_set_status($p['number'], api_str($in, 'status'), (int) $admin['id'], api_str($in, 'note'));

    $data = order_public($order);
    $data['customer']['user_id'] = $order['user_id'] !== null ? (int) $order['user_id'] : null;
    api_ok($data);
}, 'admin');

/* ---------- Customers ---------- */

// GET /api/admin/customers?q=&page=
api_route('GET', '/admin/customers', function () {
    $q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    $where = "WHERE u.role = 'customer'";
    $val   = [];
    if ($q !== '') {
        $where .= " AND (CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
        $val = [like_term($q), like_term($q)];
    }

    $stmt = db()->prepare("SELECT COUNT(*) FROM users u $where");
    $stmt->execute($val);
    $pg = admin_pagination((int) $stmt->fetchColumn(), 10);

    $stmt = db()->prepare("SELECT u.id, u.first_name, u.last_name, u.email, COUNT(o.id) AS orders
                           FROM users u LEFT JOIN orders o ON o.user_id = u.id $where
                           GROUP BY u.id ORDER BY u.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
    $stmt->execute($val);
    api_ok(
        array_map(static fn ($r) => ['id' => (int) $r['id'], 'name' => user_display_name($r), 'email' => $r['email'], 'orders' => (int) $r['orders']], $stmt->fetchAll(PDO::FETCH_ASSOC)),
        200,
        ['page' => $pg['page'], 'pages' => $pg['pages'], 'per_page' => $pg['per_page'], 'total' => $pg['total']]
    );
}, 'admin');

// GET /api/admin/customers/{id}
api_route('GET', '/admin/customers/{id}', function (array $p) {
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer'");
    $stmt->execute([(int) $p['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) api_fail(404, 'not_found', 'Customer not found.');

    $orders = orders_for_user((int) $user['id'], 200);
    $delivered = array_filter($orders, static fn ($o) => $o['status'] === 'delivered');

    api_ok([
        'id'      => (int) $user['id'],
        'name'    => user_display_name($user),
        'email'   => $user['email'],
        'phone'   => $user['phone'],
        'address' => ['line1' => $user['address_line1'], 'city' => $user['city'], 'postal' => $user['postal_code']],
        'orders'  => array_map('admin_order_row', $orders),
        'orders_count'    => count($orders),
        'delivered_sales' => array_sum(array_map(static fn ($o) => (float) $o['subtotal'], $delivered)),
        'currency'        => currency_code(),
    ]);
}, 'admin');

/* ---------- Reports ---------- */

function report_orders(): array
{
    [$sql, $val] = admin_order_filter();
    $where = $sql ? 'WHERE ' . implode(' AND ', $sql) : '';
    $stmt = db()->prepare("SELECT o.* FROM orders o $where ORDER BY o.id DESC LIMIT 5000");
    $stmt->execute($val);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function report_sales(): array
{
    [$dl, $dv] = date_clause(DELIVERED_AT_SQL, valid_date($_GET['from'] ?? null), valid_date($_GET['to'] ?? null));
    $stmt = db()->prepare('SELECT o.*, ' . DELIVERED_AT_SQL . " AS delivered_at FROM orders o WHERE o.status = 'delivered'" . ($dl ? ' AND ' . implode(' AND ', $dl) : '') . ' ORDER BY delivered_at DESC LIMIT 5000');
    $stmt->execute($dv);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// GET /api/admin/reports/orders?from=&to=&status=      GET .../orders.csv
api_route('GET', '/admin/reports/orders', function () {
    $rows = report_orders();
    api_ok(array_map('admin_order_row', $rows), 200, ['matching' => count($rows), 'recorded_total' => array_sum(array_map(static fn ($r) => (float) $r['total_amount'], $rows)), 'currency' => currency_code()]);
}, 'admin');

api_route('GET', '/admin/reports/orders.csv', function () {
    $rows = report_orders();
    api_csv('orders-' . date('Y-m-d') . '.csv', ['Order', 'Customer', 'Email', 'Placed', 'Status', 'Total (' . currency_code() . ')'],
        array_map(static fn ($r) => [$r['order_number'], $r['customer_name'], $r['customer_email'], $r['created_at'], $r['status'], $r['total_amount']], $rows));
}, 'admin');

// GET /api/admin/reports/sales?from=&to=               GET .../sales.csv   (delivered orders only)
api_route('GET', '/admin/reports/sales', function () {
    $rows  = report_sales();
    $merch = array_sum(array_map(static fn ($r) => (float) $r['subtotal'], $rows));
    $fees  = array_sum(array_map(static fn ($r) => (float) $r['shipping_fee'], $rows));
    api_ok(
        array_map(static fn ($r) => ['number' => $r['order_number'], 'customer' => $r['customer_name'], 'delivered_at' => $r['delivered_at'], 'merchandise' => (float) $r['subtotal'], 'delivery' => (float) $r['shipping_fee'], 'total' => (float) $r['total_amount']], $rows),
        200,
        ['delivered_orders' => count($rows), 'merchandise_sales' => $merch, 'delivery_fees' => $fees, 'order_totals' => $merch + $fees, 'currency' => currency_code()]
    );
}, 'admin');

api_route('GET', '/admin/reports/sales.csv', function () {
    api_csv('sales-' . date('Y-m-d') . '.csv', ['Order', 'Customer', 'Delivered', 'Merchandise (' . currency_code() . ')', 'Delivery', 'Total'],
        array_map(static fn ($r) => [$r['order_number'], $r['customer_name'], $r['delivered_at'], $r['subtotal'], $r['shipping_fee'], $r['total_amount']], report_sales()));
}, 'admin');

// GET /api/admin/reports/stock?category=&status=
api_route('GET', '/admin/reports/stock', function () {
    $cat    = is_string($_GET['category'] ?? null) ? trim($_GET['category']) : '';
    $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'all';

    $products = array_values(array_filter(all_products_admin(), static fn ($p) => $cat === '' || $p['category'] === $cat));
    $rows = [];
    $units = $low = $out = 0;
    $notes = ['low' => [], 'out' => []];

    foreach ($products as $p) {
        $variants = admin_variants([$p]);
        $pLow = count(array_filter($variants, static fn ($v) => $v['status'] === 'low'));
        $pOut = count(array_filter($variants, static fn ($v) => $v['status'] === 'out'));
        $pUnits = product_units($p);
        $attention = $pOut > 0 || $pLow > 0 ? ($pLow ? "$pLow low" : '') . ($pLow && $pOut ? ' / ' : '') . ($pOut ? "$pOut sold out" : '') : 'in';
        $pStatus = $pOut > 0 ? 'out' : ($pLow > 0 ? 'low' : 'in');

        $units += $pUnits; $low += $pLow; $out += $pOut;
        foreach ($variants as $v) {
            if ($v['status'] === 'low') $notes['low'][] = $v['sku'] . ' (' . $v['available'] . ')';
            if ($v['status'] === 'out') $notes['out'][] = $v['sku'] . ' (0)';
        }
        if (in_array($status, ['in', 'low', 'out'], true) && $pStatus !== $status) continue;
        $rows[] = ['id' => $p['id'], 'name' => $p['name'], 'available' => $pUnits, 'attention' => $attention, 'status' => $pStatus];
    }

    api_ok($rows, 200, ['units' => $units, 'low' => $low, 'out' => $out, 'notes' => $notes, 'limit' => LOW_STOCK_LIMIT]);
}, 'admin');

/* ---------- Settings ---------- */

// GET /api/admin/settings
api_route('GET', '/admin/settings', function () {
    api_ok(settings_all(), 200, ['currencies' => CURRENCIES, 'mail_configured' => MAIL_ENABLED, 'mail_file' => IS_LOCAL ? 'config/mail.local.php' : 'config/mail.remote.php']);
}, 'admin');

// PATCH /api/admin/settings
api_route('PATCH', '/admin/settings', function () {
    $in = api_input();
    $v  = [
        'store_name'       => api_str($in, 'store_name'),
        'support_email'    => api_str($in, 'support_email'),
        'support_phone'    => api_str($in, 'support_phone'),
        'store_address'    => api_str($in, 'store_address'),
        'currency'         => api_str($in, 'currency'),
        'delivery_fee'     => api_str($in, 'delivery_fee'),
        'alert_recipients' => api_str($in, 'alert_recipients'),
        'alert_extra'      => api_str($in, 'alert_extra'),
        'alert_enabled'    => !empty($in['alert_enabled']) ? '1' : '0',
    ];
    $e = [];
    $emails = static function (string $text): array {
        return array_values(array_filter(preg_split('/[\s,;]+/', $text, -1, PREG_SPLIT_NO_EMPTY)));
    };
    $allValid = static fn (array $list) => !array_filter($list, static fn ($a) => !filter_var($a, FILTER_VALIDATE_EMAIL));

    if ($v['store_name'] === '' || v_length($v['store_name']) > 80) $e['store_name'] = 'Please enter the store name (up to 80 characters).';
    if (!filter_var($v['support_email'], FILTER_VALIDATE_EMAIL)) $e['support_email'] = 'Please enter a valid support email.';
    if ($v['support_phone'] !== '' && !v_phone_ok($v['support_phone'])) $e['support_phone'] = 'Please enter a valid phone number.';
    if (v_length($v['store_address']) > 300) $e['store_address'] = 'The address is too long.';
    if (!in_array($v['currency'], CURRENCIES, true)) $e['currency'] = 'Choose a currency from the list.';
    if (!is_numeric($v['delivery_fee']) || (float) $v['delivery_fee'] < 0 || (float) $v['delivery_fee'] > 1000000) $e['delivery_fee'] = 'Delivery fee must be a number, zero or more.';

    $main  = $emails($v['alert_recipients']);
    $extra = $emails($v['alert_extra']);
    if (!$allValid($main) || ($v['alert_enabled'] === '1' && !$main)) $e['alert_recipients'] = 'Enter at least one valid email address (separate several with commas).';
    if (!$allValid($extra)) $e['alert_extra'] = 'One of the additional addresses is not a valid email.';

    if ($e) api_fail(422, 'validation_failed', 'Please check the highlighted fields.', $e);

    $v['delivery_fee']     = (string) round((float) $v['delivery_fee'], 2);
    $v['alert_recipients'] = implode(', ', $main);
    $v['alert_extra']      = implode(', ', $extra);
    settings_save($v);
    api_ok(settings_all(true));
}, 'admin');

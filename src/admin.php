<?php
/**
 * Back-office logic for the admin API: products, variant stock, categories, orders, customers, reports, settings.
 * Everything here runs only after api_require_admin() has passed (see api/routes/admin.php).
 */

const LOW_STOCK_LIMIT   = 3;                       // 1..3 units = "Low stock", 0 = "Out of stock"
const ORDER_STATUSES    = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
const MAX_IMAGE_BYTES   = 3145728;                 // 3 MB
const MAX_IMAGES        = 6;

/* ==========================================================================
   Small helpers
   ========================================================================== */

function stock_status(int $qty): string
{
    return $qty < 1 ? 'out' : ($qty <= LOW_STOCK_LIMIT ? 'low' : 'in');
}

/** TEE-001 + Chalk + XS  ->  TEE-001-CH-XS */
function variant_sku(string $sku, string $color, string $size): string
{
    return $sku . '-' . strtoupper(substr((string) preg_replace('/[^A-Za-z]/', '', $color), 0, 2)) . '-' . $size;
}

/** "%" and "_" typed by the admin must not act as wildcards in LIKE. */
function like_term(string $text): string
{
    return '%' . addcslashes($text, '%_\\') . '%';
}

function admin_pagination(int $total, int $perPage): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min(api_int_query('page', 1), $pages);
    return ['page' => $page, 'pages' => $pages, 'per_page' => $perPage, 'total' => $total, 'offset' => ($page - 1) * $perPage];
}

function valid_date($value): ?string
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }
    [$y, $m, $d] = array_map('intval', explode('-', $value));
    return checkdate($m, $d, $y) ? $value : null;
}

/** [from, to] (Y-m-d) for the dashboard period choices; null = no limit. */
function period_range(string $period): array
{
    $today = new DateTimeImmutable('today');
    switch ($period) {
        case 'last_30':    return [$today->modify('-29 days')->format('Y-m-d'), $today->format('Y-m-d'), 'Last 30 days'];
        case 'last_month': return [$today->modify('first day of last month')->format('Y-m-d'), $today->modify('last day of last month')->format('Y-m-d'), 'Last month'];
        case 'all':        return [null, null, 'All time'];
        default:           return [$today->modify('first day of this month')->format('Y-m-d'), $today->format('Y-m-d'), 'This month'];
    }
}

/** SQL for "placed between", with its values. */
function date_clause(string $column, ?string $from, ?string $to): array
{
    $sql = [];
    $val = [];
    if ($from !== null) { $sql[] = "$column >= ?";                           $val[] = $from; }
    if ($to !== null)   { $sql[] = "$column < DATE_ADD(?, INTERVAL 1 DAY)";  $val[] = $to; }
    return [$sql, $val];
}

/** When an order was delivered (from its history; older orders fall back to the last update). */
const DELIVERED_AT_SQL = "COALESCE((SELECT MIN(h.created_at) FROM order_status_history h WHERE h.order_id = o.id AND h.status = 'delivered'), o.updated_at)";

/* ==========================================================================
   Products + variants
   ========================================================================== */

/** Every size/colour combination of the given products with its stock status. */
function admin_variants(array $products): array
{
    $rows = [];
    foreach ($products as $p) {
        foreach ($p['stock'] as $color => $bySize) {
            foreach ($bySize as $size => $qty) {
                $rows[] = [
                    'product_id' => $p['id'],
                    'product'    => $p['name'],
                    'product_sku' => $p['sku'],
                    'sku'        => variant_sku($p['sku'], (string) $color, (string) $size),
                    'color'      => (string) $color,
                    'size'       => (string) $size,
                    'available'  => (int) $qty,
                    'status'     => stock_status((int) $qty),
                ];
            }
        }
    }
    return $rows;
}

function admin_product_view(array $p): array
{
    return [
        'id'          => $p['id'],
        'sku'         => $p['sku'],
        'slug'        => $p['slug'],
        'name'        => $p['name'],
        'category'    => $p['category'],
        'price'       => $p['price'],
        'description' => (string) $p['description'],
        'published'   => $p['is_active'],
        'units'       => product_units($p),
        'variants'    => admin_variants([$p]),
        'images'      => array_map(static fn ($path) => ['path' => $path, 'url' => product_image_url($path)], $p['images']),
        'image_alt'   => (string) $p['image_alt'],
        'url'         => product_url($p['slug']),
    ];
}

function admin_find_product(int $id): ?array
{
    foreach (all_products_admin() as $p) {
        if ($p['id'] === $id) return $p;
    }
    return null;
}

function unique_slug(string $table, string $text, int $exceptId = 0): string
{
    $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-') ?: 'item';
    $slug = $base;
    for ($i = 2; ; $i++) {
        $stmt = db()->prepare("SELECT COUNT(*) FROM $table WHERE slug = ? AND id <> ?");
        $stmt->execute([$slug, $exceptId]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $base . '-' . $i;
    }
}

/** Check + clean a product form. Returns [clean, errors]. */
function admin_validate_product(array $in, int $exceptId = 0): array
{
    $c = [
        'name'        => api_str($in, 'name'),
        'sku'         => api_str($in, 'sku'),
        'category'    => api_str($in, 'category'),
        'description' => api_str($in, 'description'),
        'image_alt'   => api_str($in, 'image_alt'),
        'published'   => !empty($in['published']) ? 1 : 0,
    ];
    $e = [];

    if ($c['name'] === '' || v_length($c['name']) > 190) $e['name'] = 'Please enter the product name.';
    if (!preg_match('/^[A-Za-z0-9._-]{1,60}$/', $c['sku'])) {
        $e['sku'] = 'SKU can use letters, numbers, dots, dashes and underscores (up to 60).';
    } else {
        $stmt = db()->prepare('SELECT COUNT(*) FROM products WHERE sku = ? AND id <> ?');
        $stmt->execute([$c['sku'], $exceptId]);
        if ((int) $stmt->fetchColumn() > 0) $e['sku'] = 'Another product already uses this SKU.';
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM categories WHERE name = ?');
    $stmt->execute([$c['category']]);
    if ($c['category'] === '' || (int) $stmt->fetchColumn() === 0) $e['category'] = 'Please choose a category.';

    $price = $in['price'] ?? '';
    if (!is_numeric($price) || (float) $price < 0 || (float) $price > 99999999) {
        $e['price'] = 'Price must be a number, zero or more.';
    }
    $c['price'] = is_numeric($price) ? round((float) $price, 2) : 0;

    if ($c['description'] === '' || v_length($c['description']) > 5000) $e['description'] = 'Please add a description (up to 5000 characters).';
    if (v_length($c['image_alt']) > 190) $e['image_alt'] = 'Alternative text is too long.';

    // Variants: [{color, size, stock}] -> stock[colour][size]
    $stock = [];
    $seen  = [];
    foreach ((array) ($in['variants'] ?? []) as $i => $v) {
        $color = is_array($v) ? api_str($v, 'color') : '';
        $size  = is_array($v) ? api_str($v, 'size') : '';
        $qty   = is_array($v) ? ($v['stock'] ?? '') : '';

        if (!preg_match('/^[\p{L}][\p{L} \-]{0,29}$/u', $color) || !preg_match('/^[A-Za-z0-9]{1,10}$/', $size)) {
            $e['variants'] = 'Every variant needs a color and a size.';
            continue;
        }
        if (!is_numeric($qty) || (int) $qty != $qty || (int) $qty < 0 || (int) $qty > 100000) {
            $e['variants'] = 'Stock uses whole numbers, zero or greater.';
            continue;
        }
        $key = strtolower($color . '|' . $size);
        if (isset($seen[$key])) {
            $e['variants'] = 'Every size and color combination must be unique.';
            continue;
        }
        $seen[$key] = true;
        $stock[$color][strtoupper($size)] = (int) $qty;
    }
    if (!$stock && !isset($e['variants'])) {
        $e['variants'] = 'Add at least one size and color variant.';
    }
    $c['stock'] = $stock;

    return [$c, $e];
}

/** Create (id = 0) or update a product. Returns its id. */
function admin_save_product(int $id, array $c): int
{
    $pdo    = db();
    $colors = json_encode(array_keys($c['stock']), JSON_UNESCAPED_UNICODE);
    $stock  = json_encode($c['stock'], JSON_UNESCAPED_UNICODE);

    if ($id === 0) {
        $pdo->prepare(
            'INSERT INTO products (sku, name, slug, description, category, price, colors, stock, images, is_active, image_alt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, "[]", ?, ?)'
        )->execute([$c['sku'], $c['name'], unique_slug('products', $c['name']), $c['description'], $c['category'], $c['price'], $colors, $stock, $c['published'], $c['image_alt'] ?: null]);
        return (int) $pdo->lastInsertId();
    }

    $pdo->prepare(
        'UPDATE products SET sku = ?, name = ?, description = ?, category = ?, price = ?, colors = ?, stock = ?, is_active = ?, image_alt = ? WHERE id = ?'
    )->execute([$c['sku'], $c['name'], $c['description'], $c['category'], $c['price'], $colors, $stock, $c['published'], $c['image_alt'] ?: null, $id]);
    return $id;
}

/** Store a validated image, using quality-conscious WebP compression where GD supports it.
 * Only the relative path is saved in the products.images JSON database column.
 */
function admin_store_image(array $file): string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        api_fail(422, 'upload_failed', 'Upload failed (code ' . $error . '). Check the server upload size limit.');
    }
    if (!is_uploaded_file((string) ($file['tmp_name'] ?? '')) || (int) ($file['size'] ?? 0) > MAX_IMAGE_BYTES) {
        api_fail(422, 'upload_failed', 'Choose an image no larger than 3 MB.');
    }
    $info = @getimagesize($file['tmp_name']);
    $type = (int) ($info[2] ?? 0);
    if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
        api_fail(422, 'upload_failed', 'Only JPG, PNG, WEBP and GIF images are supported.');
    }
    if (($info[0] ?? 0) * ($info[1] ?? 0) > 36000000) {
        api_fail(422, 'upload_failed', 'Image dimensions are too large.');
    }
    $dir = __DIR__ . '/../assets/uploads/products';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) api_fail(500, 'server_error', 'Upload directory unavailable.');
    $extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    $ext = $extensions[$type];
    $name = bin2hex(random_bytes(10));
    $target = $dir . '/' . $name . '.' . $ext;
    $saved = false;
    // Preserve animated GIFs; for still images, optimize to WebP (quality 85).
    if ($type !== IMAGETYPE_GIF && function_exists('imagewebp')) {
        $loaders = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
        $loader = $loaders[$type] ?? null;
        if ($loader && function_exists($loader)) {
            $source = @$loader($file['tmp_name']);
            if ($source !== false) {
                $width = imagesx($source); $height = imagesy($source);
                $scale = min(1, 2000 / max($width, $height));
                $newW = max(1, (int) round($width * $scale));
                $newH = max(1, (int) round($height * $scale));
                $canvas = imagecreatetruecolor($newW, $newH);
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                imagefill($canvas, 0, 0, $transparent);
                imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newW, $newH, $width, $height);
                $candidate = $dir . '/' . $name . '.webp';
                if (@imagewebp($canvas, $candidate, 85) && is_file($candidate) && filesize($candidate) > 0 && filesize($candidate) < $file['size']) {
                    $target = $candidate;
                    $ext = 'webp';
                    $saved = true;
                } else {
                    @unlink($candidate);
                }
                imagedestroy($canvas);
                imagedestroy($source);
            }
        }
    }
    if (!$saved && !move_uploaded_file($file['tmp_name'], $target)) {
        api_fail(500, 'server_error', 'Image could not be saved. Check upload folder permissions.');
    }
    return 'assets/uploads/products/' . $name . '.' . $ext;
}

/* ==========================================================================
   Orders
   ========================================================================== */

/**
 * Change an order's status. Cancelling puts the items back into stock.
 * pending -> confirmed -> shipped -> delivered (forward only); an order can be cancelled until it is delivered.
 */
function order_set_status(string $number, string $newStatus, int $adminId, string $note = ''): array
{
    if (!in_array($newStatus, ORDER_STATUSES, true)) {
        api_fail(422, 'validation_failed', 'Choose a valid status.', ['status' => 'Choose a valid status.']);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = ? FOR UPDATE');
        $stmt->execute([$number]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            api_fail(404, 'not_found', 'Order not found.');
        }

        $current = $order['status'];
        $step    = ['pending' => 0, 'confirmed' => 1, 'processing' => 1, 'shipped' => 2, 'delivered' => 3];

        if (in_array($current, ['delivered', 'cancelled', 'refunded'], true)) {
            api_fail(409, 'final_status', 'This order is already ' . $current . ' and cannot be changed.');
        }
        $allowed = $newStatus === 'cancelled' || (isset($step[$current]) && $step[$newStatus] > $step[$current]);
        if (!$allowed) {
            api_fail(422, 'invalid_transition', 'An order cannot go from ' . $current . ' to ' . $newStatus . '.', ['status' => 'Choose a later status, or cancel the order.']);
        }

        $pdo->prepare(
            "UPDATE orders SET status = ?,
                payment_status = IF(? = 'delivered', 'paid', payment_status),
                paid_at = IF(? = 'delivered', NOW(), paid_at) WHERE id = ?"
        )->execute([$newStatus, $newStatus, $newStatus, $order['id']]);

        if ($newStatus === 'cancelled') {
            $items = $pdo->prepare('SELECT product_id, color, size, quantity FROM order_items WHERE order_id = ? AND product_id IS NOT NULL');
            $items->execute([$order['id']]);
            $read   = $pdo->prepare('SELECT stock FROM products WHERE id = ? FOR UPDATE');
            $update = $pdo->prepare('UPDATE products SET stock = ? WHERE id = ?');
            foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $read->execute([$item['product_id']]);
                $json = $read->fetchColumn();
                if ($json === false) continue;
                $stock = json_decode((string) $json, true) ?: [];
                $stock[$item['color']][$item['size']] = (int) ($stock[$item['color']][$item['size']] ?? 0) + (int) $item['quantity'];
                $update->execute([json_encode($stock, JSON_UNESCAPED_UNICODE), $item['product_id']]);
            }
        }

        $defaultNote = ['confirmed' => 'Order confirmed by the store.', 'shipped' => 'Your order is on its way.', 'delivered' => 'Order delivered.', 'cancelled' => 'Order cancelled; items returned to stock.'];
        $pdo->prepare('INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)')
            ->execute([$order['id'], $newStatus, $note !== '' ? $note : ($defaultNote[$newStatus] ?? null), $adminId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $saved = find_order_by_number($number);
    if (MAIL_ENABLED && $saved) {
        try {
            send_order_status_email($saved, $newStatus);
        } catch (Throwable $e) {
            error_log('Status e-mail failed: ' . $e->getMessage());
        }
    }
    return $saved;
}

/* ==========================================================================
   CSV
   ========================================================================== */

/** Cell text that spreadsheets cannot run as a formula (= + - @ at the start). */
function csv_cell($value): string
{
    $text = (string) $value;
    return $text !== '' && strpos("=+-@\t\r", $text[0]) !== false ? "'" . $text : $text;
}

function api_csv(string $filename, array $header, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");                          // so Excel reads UTF-8
    fputcsv($out, $header);
    foreach ($rows as $row) {
        fputcsv($out, array_map('csv_cell', $row));
    }
    fclose($out);
    exit;
}

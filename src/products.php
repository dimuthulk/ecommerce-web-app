<?php
/** Product lookups shared by the pages and the API. Data comes from all_products() (the products table). */

const SIZE_ORDER = ['XS', 'S', 'M', 'L', 'XL'];

/** The sizes a product comes in (any colour), in the usual order. */
function product_sizes(array $p): array
{
    $sizes = [];
    foreach ($p['stock'] as $bySize) {
        foreach (array_keys($bySize) as $size) {
            $sizes[$size] = true;
        }
    }
    $known = array_values(array_filter(SIZE_ORDER, static fn ($s) => isset($sizes[$s])));
    $other = array_values(array_diff(array_keys($sizes), SIZE_ORDER));
    return array_merge($known, $other);
}

function product_units(array $p): int
{
    $units = 0;
    foreach ($p['stock'] as $bySize) {
        $units += array_sum(array_map('intval', $bySize));
    }
    return $units;
}

function product_find_by_slug(string $slug): ?array
{
    foreach (all_products() as $p) {
        if ($p['slug'] === $slug) return $p;
    }
    return null;
}

function product_find_by_id(int $id): ?array
{
    foreach (all_products() as $p) {
        if ($p['id'] === $id) return $p;
    }
    return null;
}

/** What the API shows for a product. */
function product_public(array $p): array
{
    return [
        'id'          => $p['id'],
        'sku'         => $p['sku'],
        'slug'        => $p['slug'],
        'url'         => product_url($p['slug']),
        'name'        => $p['name'],
        'category'    => $p['category'],
        'description' => $p['description'],
        'price'       => $p['price'],
        'sale_price'  => $p['sale_price'],
        'colors'      => $p['colors'],
        'sizes'       => product_sizes($p),
        'stock'       => $p['stock'],                    // { colour: { size: quantity } }
        'in_stock'    => product_units($p) > 0,
        'images'      => array_map(static fn ($path) => url($path), $p['images']),
    ];
}

/** Categories shown in the store (the visible ones from the categories table, else those products use). */
function store_categories(): array
{
    try {
        $rows = db()->query('SELECT name FROM categories WHERE is_visible = 1 ORDER BY sort_order, name')->fetchAll(PDO::FETCH_COLUMN);
        if ($rows) return $rows;
    } catch (Throwable $e) {
        // table not created yet
    }
    return array_values(array_unique(array_column(all_products(), 'category')));
}

/**
 * Search / filter / sort / paginate the products.
 * $params: q, category[], size[], color[], stock (1 = in stock only), sort, page, per_page
 */
function products_query(array $params, int $defaultPerPage = 6): array
{
    $list = static function ($v): array {
        $v = array_filter((array) $v, 'is_scalar');
        return array_values(array_filter(array_map(static fn ($x) => trim((string) $x), $v), 'strlen'));
    };

    $q      = isset($params['q']) && is_string($params['q']) ? trim($params['q']) : '';
    $cats   = $list($params['category'] ?? []);
    $sizes  = $list($params['size'] ?? []);
    $colors = $list($params['color'] ?? []);
    $onlyIn = !empty($params['stock']);
    $sort   = isset($params['sort']) && is_string($params['sort']) ? $params['sort'] : 'recommended';
    $page   = max(1, (int) ($params['page'] ?? 1));
    $per    = max(1, min(48, (int) ($params['per_page'] ?? $defaultPerPage)));

    $price = static fn (array $p): float => (float) ($p['sale_price'] ?: $p['price']);

    $filtered = array_values(array_filter(all_products(), static function (array $p) use ($q, $cats, $sizes, $colors, $onlyIn): bool {
        $check = $colors ? array_values(array_intersect($p['colors'], $colors)) : $p['colors'];
        if ($colors && !$check) return false;

        $inStock = [];
        foreach ($check as $c) {
            foreach ($p['stock'][$c] ?? [] as $size => $qty) {
                if ($qty > 0) $inStock[$size] = true;
            }
        }
        $inStock = array_keys($inStock);

        if ($q !== '' && stripos($p['name'] . ' ' . $p['category'] . ' ' . implode(' ', $p['colors']), $q) === false) return false;
        if ($cats && !in_array($p['category'], $cats, true)) return false;
        if ($sizes && !array_intersect($inStock, $sizes)) return false;
        if ($onlyIn && !$inStock) return false;
        return true;
    }));

    if ($sort === 'price_asc')  usort($filtered, static fn ($a, $b) => $price($a) <=> $price($b));
    if ($sort === 'price_desc') usort($filtered, static fn ($a, $b) => $price($b) <=> $price($a));
    if ($sort === 'name')       usort($filtered, static fn ($a, $b) => strcasecmp($a['name'], $b['name']));

    $total = count($filtered);
    $pages = max(1, (int) ceil($total / $per));
    $page  = min($page, $pages);

    return [
        'items'    => array_slice($filtered, ($page - 1) * $per, $per),
        'total'    => $total,
        'page'     => $page,
        'pages'    => $pages,
        'per_page' => $per,
    ];
}

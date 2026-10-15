<?php
require_once __DIR__ . '/config/app.php';

// Sample products for now (data/products.php). Swap for a database query later.
$allProducts = require __DIR__ . '/data/products.php';

/* ---------- 1. Read + sanitise the query string ---------- */
$readList = static function (string $key): array {
    $values = array_filter((array) ($_GET[$key] ?? []), 'is_scalar');
    $values = array_map(static fn ($v) => trim((string) $v), $values);
    return array_values(array_filter($values, 'strlen'));
};

$q           = (isset($_GET['q']) && is_string($_GET['q'])) ? trim($_GET['q']) : '';
if (strlen($q) > 80) {
    $q = function_exists('mb_substr') ? mb_substr($q, 0, 80) : substr($q, 0, 80);
}
$selCats     = $readList('category');
$selSizes    = $readList('size');
$selColors   = $readList('color');
$inStockOnly = !empty($_GET['stock']);

$sortOptions = [
    'recommended' => 'Recommended',
    'price_asc'   => 'Price: low to high',
    'price_desc'  => 'Price: high to low',
    'name'        => 'Name: A to Z',
];
$sort = (isset($_GET['sort']) && is_string($_GET['sort']) && isset($sortOptions[$_GET['sort']]))
    ? $_GET['sort']
    : 'recommended';

$perPage = 6;
$page    = max(1, (int) ($_GET['page'] ?? 1));

/* ---------- 2. Filter options (built from the product data) ---------- */
$categories = array_values(array_unique(array_column($allProducts, 'category')));
$sizes      = ['XS', 'S', 'M', 'L', 'XL'];
$colors     = array_values(array_unique(array_merge(...array_column($allProducts, 'colors'))));

/* ---------- 3. Filter, sort, paginate ---------- */
$price = static fn (array $p): float => (float) ($p['sale_price'] ?: $p['price']);

$filtered = array_values(array_filter($allProducts, static function (array $p) use (
    $q, $selCats, $selSizes, $selColors, $inStockOnly
): bool {
    // With a colour filter, only those colours count; sizes in stock = qty > 0 in any counted colour
    $colorsToCheck = $selColors ? array_values(array_intersect($p['colors'], $selColors)) : $p['colors'];
    if ($selColors && !$colorsToCheck) return false;

    $inStock = [];
    foreach ($colorsToCheck as $c) {
        foreach ($p['stock'][$c] ?? [] as $size => $qty) {
            if ($qty > 0) $inStock[$size] = true;
        }
    }
    $inStock = array_keys($inStock);

    if ($q !== '' && stripos($p['name'] . ' ' . $p['category'] . ' ' . implode(' ', $p['colors']), $q) === false) return false;
    if ($selCats && !in_array($p['category'], $selCats, true))      return false;
    if ($selSizes && !array_intersect($inStock, $selSizes))         return false;
    if ($inStockOnly && !$inStock)                                  return false;
    return true;
}));

if ($sort === 'price_asc')  usort($filtered, static fn ($a, $b) => $price($a) <=> $price($b));
if ($sort === 'price_desc') usort($filtered, static fn ($a, $b) => $price($b) <=> $price($a));
if ($sort === 'name')       usort($filtered, static fn ($a, $b) => strcasecmp($a['name'], $b['name']));

$total      = count($filtered);
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);
$items      = array_slice($filtered, ($page - 1) * $perPage, $perPage);
$from       = $total ? ($page - 1) * $perPage + 1 : 0;
$to         = min($total, $page * $perPage);

/* ---------- 4. Helpers for the view ---------- */
$activeFilterCount = count($selCats) + count($selSizes) + count($selColors) + ($inStockOnly ? 1 : 0);

// Query string that keeps the current filters (used by pagination links)
$baseQuery = array_filter([
    'q'        => $q,
    'category' => $selCats,
    'size'     => $selSizes,
    'color'    => $selColors,
    'stock'    => $inStockOnly ? 1 : null,
    'sort'     => $sort !== 'recommended' ? $sort : null,
], static fn ($v) => $v !== null && $v !== '' && $v !== []);

$buildUrl = static function (array $query) {
    $qs = preg_replace('/%5B[0-9]+%5D/', '%5B%5D', http_build_query($query));   // category[]=... instead of category[0]=...
    return url('shop.php') . ($qs !== '' ? '?' . $qs : '');
};
$pageUrl  = static fn (int $n): string => $buildUrl($baseQuery + ($n > 1 ? ['page' => $n] : []));
$clearUrl = $buildUrl(array_intersect_key($baseQuery, ['q' => 1, 'sort' => 1]));   // clears filters, keeps search + sort

$pageTitle       = 'Everyday clothing';
$pageDescription = 'Considered pieces, made to be worn your way. Browse the full everyday clothing collection.';
require_once __DIR__ . '/components/header.php';
?>

<div class="container listing">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li><a href="<?= url('/') ?>">Home</a></li>
            <li aria-current="page">Listing</li>
        </ol>
    </nav>

    <header class="listing-head">
        <h1>Everyday clothing</h1>
        <p>Considered pieces, made to be worn your way.</p>
    </header>

    <form class="listing-form" method="get" action="<?= url('shop.php') ?>" data-autosubmit>

        <div class="field search-field">
            <label for="search">Search clothing</label>
            <input class="input" type="search" id="search" name="q" value="<?= e($q) ?>" placeholder="Search all clothing" autocomplete="off">
            <button type="submit" class="sr-only">Search</button>
        </div>

        <div class="listing-toolbar">
            <p class="results-count"><?= $total ?> product<?= $total === 1 ? '' : 's' ?> &middot; <?= e($selCats ? implode(', ', $selCats) : 'All clothing') ?></p>

            <button type="button" class="btn btn--outline filters-toggle" aria-expanded="false" aria-controls="filters-panel">Filters (<?= $activeFilterCount ?>)</button>

            <div class="field sort-field">
                <label for="sort">Sort by</label>
                <select class="select" id="sort" name="sort">
                    <?php foreach ($sortOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= $sort === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="listing-layout">

            <aside class="filters" id="filters-panel" aria-label="Filters">
                <h2 class="filters__title">Filters</h2>

                <fieldset class="filter-group">
                    <legend>Category</legend>
                    <label class="check">
                        <input type="checkbox" data-all-for="category"<?= $selCats ? '' : ' checked' ?>>
                        <span>All clothing</span>
                    </label>
                    <?php foreach ($categories as $category): ?>
                    <label class="check">
                        <input type="checkbox" name="category[]" value="<?= e($category) ?>"<?= in_array($category, $selCats, true) ? ' checked' : '' ?>>
                        <span><?= e($category) ?></span>
                    </label>
                    <?php endforeach; ?>
                </fieldset>

                <fieldset class="filter-group">
                    <legend>Size</legend>
                    <div class="size-grid">
                        <?php foreach ($sizes as $size): ?>
                        <label class="size-chip">
                            <input type="checkbox" name="size[]" value="<?= e($size) ?>"<?= in_array($size, $selSizes, true) ? ' checked' : '' ?>>
                            <span><?= e($size) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <fieldset class="filter-group">
                    <legend>Color</legend>
                    <?php foreach ($colors as $color): ?>
                    <label class="check">
                        <input type="checkbox" name="color[]" value="<?= e($color) ?>"<?= in_array($color, $selColors, true) ? ' checked' : '' ?>>
                        <span><?= e($color) ?></span>
                    </label>
                    <?php endforeach; ?>
                </fieldset>

                <fieldset class="filter-group">
                    <legend>Availability</legend>
                    <label class="check">
                        <input type="checkbox" name="stock" value="1"<?= $inStockOnly ? ' checked' : '' ?>>
                        <span>In stock only</span>
                    </label>
                </fieldset>

                <a class="btn btn--ghost" href="<?= $clearUrl ?>">Clear filters</a>

                <noscript><button type="submit" class="btn btn--primary">Apply filters</button></noscript>
            </aside>

            <div class="listing-main">

                <div class="active-filters" aria-label="Active filters">
                    <span class="chip">Category: <?= e($selCats ? implode(', ', $selCats) : 'All') ?></span>
                    <span class="chip">Size: <?= e($selSizes ? implode(', ', $selSizes) : 'All') ?></span>
                    <?php if ($selColors): ?><span class="chip">Color: <?= e(implode(', ', $selColors)) ?></span><?php endif; ?>
                    <?php if ($inStockOnly): ?><span class="chip">In stock only</span><?php endif; ?>

                    <?php if ($activeFilterCount): ?>
                        <a class="btn btn--ghost" href="<?= $clearUrl ?>">Clear filters</a>
                    <?php else: ?>
                        <span class="active-filters__none">No filters</span>
                    <?php endif; ?>
                </div>

                <?php if ($items): ?>
                <div class="product-grid product-grid--listing">
                    <?php foreach ($items as $product): ?>
                        <?php include __DIR__ . '/components/product-card.php'; ?>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <h2 class="card-title">No products found</h2>
                    <p>Try removing a filter or searching for something else.</p>
                    <a class="btn btn--outline" href="<?= url('shop.php') ?>">Clear all</a>
                </div>
                <?php endif; ?>

                <?php if ($total): ?>
                <nav class="pagination" aria-label="Pagination">
                    <p class="pagination__summary">Showing <?= $from ?>&ndash;<?= $to ?> of <?= $total ?></p>

                    <?php if ($totalPages > 1): ?>
                        <?php if ($page > 1): ?>
                            <a class="page-link" href="<?= $pageUrl($page - 1) ?>" rel="prev">Previous</a>
                        <?php else: ?>
                            <span class="page-link" aria-disabled="true">Previous</span>
                        <?php endif; ?>

                        <?php for ($n = 1; $n <= $totalPages; $n++): ?>
                            <a class="page-link page-link--num" href="<?= $pageUrl($n) ?>"<?= $n === $page ? ' aria-current="page"' : '' ?> aria-label="Page <?= $n ?>"><?= $n ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a class="page-link" href="<?= $pageUrl($page + 1) ?>" rel="next">Next</a>
                        <?php else: ?>
                            <span class="page-link" aria-disabled="true">Next</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>

            </div>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>
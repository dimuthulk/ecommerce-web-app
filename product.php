<?php
require_once __DIR__ . '/config/app.php';

// Products come from the database (all_products() in config/app.php)
$allProducts = all_products();

/* ---------- 1. Find the product (URL: /product/essential-cotton-tee) ---------- */
$slug    = (isset($_GET['slug']) && is_string($_GET['slug'])) ? $_GET['slug'] : '';
$product = null;
foreach ($allProducts as $candidate) {
    if ($candidate['slug'] === $slug) {
        $product = $candidate;
        break;
    }
}

if ($product === null) {
    http_response_code(404);
    $pageTitle = 'Product not found';
    require_once __DIR__ . '/components/header.php';
    ?>
    <section class="container section">
        <div class="empty-state">
            <h1 class="card-title">Product not found</h1>
            <p>This product may have been removed or the link is incorrect.</p>
            <a class="btn btn--outline" href="<?= url('shop.php') ?>">Back to shop</a>
        </div>
    </section>
    <?php
    require_once __DIR__ . '/components/footer.php';
    exit;
}

/* ---------- 2. Stock helpers ---------- */
$sizeOrder = ['XS', 'S', 'M', 'L', 'XL'];
$colors    = $product['colors'];
$stock     = $product['stock'];                         // [ colour => [ size => qty ] ]

$qtyFor = static fn (string $c, string $s): int => (int) ($stock[$c][$s] ?? 0);

// Sizes this product comes in (any colour), in the usual order
$allSizes = array_values(array_filter($sizeOrder, static function (string $s) use ($stock): bool {
    foreach ($stock as $bySize) {
        if (isset($bySize[$s])) return true;
    }
    return false;
}));

/* ---------- 3. Add to bag (POST) ---------- */
$formError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedColor = is_string($_POST['color'] ?? null) ? $_POST['color'] : '';
    $postedSize  = is_string($_POST['size'] ?? null) ? $_POST['size'] : '';
    $qty         = max(1, (int) ($_POST['qty'] ?? 1));
    $available   = $qtyFor($postedColor, $postedSize);

    if (!csrf_valid()) {
        $formError = 'Your session expired. Please try again.';
    } elseif (!in_array($postedColor, $colors, true) || $available < 1) {
        $formError = 'Please choose an available size.';
    } else {
        $key    = cart_key((int) $product['id'], $postedColor, $postedSize);
        $before = (int) ($_SESSION['cart'][$key] ?? 0);
        $after  = min($before + $qty, $available);
        $_SESSION['cart'][$key] = $after;

        $what = $product['name'] . ' · ' . $postedColor . ' / ' . $postedSize;
        $view = ['action_label' => 'View bag', 'action_url' => url('cart.php')];

        if ($after === $before) {
            toast('Already at the maximum', 'info', ['text' => 'Only ' . $available . ' available in ' . $postedColor . ' / ' . $postedSize . '.'] + $view);
        } elseif ($after < $before + $qty) {
            toast('Added to bag', 'success', ['text' => $what . ' · only ' . $available . ' available'] + $view);
        } else {
            toast('Added to bag', 'success', ['text' => $what . ($qty > 1 ? ' · Qty ' . $qty : '')] + $view);
        }
        header('Location: ' . product_url($product['slug']) . '?' . http_build_query(['color' => $postedColor, 'size' => $postedSize]) . '#purchase');
        exit;
    }
}

/* ---------- 4. Selected colour + size (from the URL, the form, or sensible defaults) ---------- */
$requestedColor = $_POST['color'] ?? $_GET['color'] ?? null;
$color = (is_string($requestedColor) && in_array($requestedColor, $colors, true)) ? $requestedColor : '';
if ($color === '') {
    $color = $colors[0];
    foreach ($colors as $c) {                           // first colour that still has stock
        if (array_sum($stock[$c] ?? []) > 0) { $color = $c; break; }
    }
}

$requestedSize = $_POST['size'] ?? $_GET['size'] ?? null;
$size = (is_string($requestedSize) && $qtyFor($color, $requestedSize) > 0) ? $requestedSize : '';
if ($size === '') {
    $inStockSizes = array_values(array_filter($allSizes, static fn (string $s): bool => $qtyFor($color, $s) > 0));
    $size = in_array('M', $inStockSizes, true) ? 'M' : ($inStockSizes[0] ?? '');
}

$available    = $size !== '' ? $qtyFor($color, $size) : 0;
$soldOutSizes = array_values(array_filter($allSizes, static fn (string $s): bool => isset($stock[$color][$s]) && $stock[$color][$s] < 1));
$allSoldOut   = count($soldOutSizes) === count(array_filter($allSizes, static fn (string $s): bool => isset($stock[$color][$s])));

$soldOutNote = '';
if ($allSoldOut) {
    $soldOutNote = $color . ' is sold out. Try another color.';
} elseif ($soldOutSizes) {
    $soldOutNote = implode(', ', $soldOutSizes) . ' / ' . $color . ' is sold out. Select an available size.';
}
$availability = $size !== '' ? $color . ' / ' . $size . ' · ' . $available . ' available' : $color . ' is sold out';

/* ---------- 5. Page content ---------- */
$description = trim((string) ($product['description'] ?? '')) !== '' ? trim((string) $product['description']) : $product['name'];
$details     = trim((string) ($product['details'] ?? ''));          // optional extra text; the section still shows without it
$collection  = $product['collection']  ?? 'Everyday essentials';

$views = ['Front view', 'Back view', 'Fabric detail', 'Fit reference'];
$media = static function (int $i, bool $main) use ($product, $views): string {
    $img = $product['images'][$i] ?? null;
    if ($img) {
        return '<img src="' . e(url($img)) . '" alt="' . e($product['name'] . ' / ' . strtolower($views[$i])) . '">';
    }
    $caption = $main ? $product['name'] . ' / ' . strtolower($views[$i]) : $views[$i];
    return image_placeholder($caption, true, 'gallery');
};

// "Complete your everyday edit": other products, different categories first
$related = array_values(array_filter($allProducts, static fn (array $p): bool => $p['id'] !== $product['id'] && $p['category'] !== $product['category']));
$related = array_slice($related, 0, 3);

$pageTitle       = $product['name'];
$pageDescription = $description;

// Search + sharing: Open Graph picture and Product structured data (read by the header)
$ogType  = 'product';
$ogImage = !empty($product['images'][0]) ? (string) $product['images'][0] : null;

$totalStock = 0;
foreach ($stock as $bySize) {
    $totalStock += array_sum(array_map('intval', (array) $bySize));
}
$jsonLd = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Product',
    'name'        => $product['name'],
    'sku'         => $product['sku'],
    'description' => $description,
    'category'    => $product['category'],
    'url'         => absolute_url('product/' . rawurlencode((string) $product['slug'])),
    'brand'       => ['@type' => 'Brand', 'name' => app_name()],
    'offers'      => [
        '@type'         => 'Offer',
        'url'           => absolute_url('product/' . $product['slug']),
        'priceCurrency' => currency_code(),
        'price'         => number_format((float) (!empty($product['sale_price']) ? $product['sale_price'] : $product['price']), 2, '.', ''),
        'availability'  => 'https://schema.org/' . ($totalStock > 0 ? 'InStock' : 'OutOfStock'),
        'itemCondition' => 'https://schema.org/NewCondition',
    ],
];
if (!empty($product['images'])) {
    $jsonLd['image'] = array_map(static fn ($img): string => absolute_url((string) $img), array_values($product['images']));
}

require_once __DIR__ . '/components/header.php';
?>

<div class="container product-page">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li><a href="<?= url('/') ?>">Home</a></li>
            <li><a href="<?= url('shop.php') ?>">Shop</a></li>
            <li aria-current="page"><?= e($product['name']) ?></li>
        </ol>
    </nav>

    <div class="product-detail">

        <!-- Gallery -->
        <div class="gallery" data-gallery>
            <div class="gallery__frame gallery__frame--main" data-label="<?= e($views[0]) ?>">
                <?= $media(0, true) ?>
            </div>
            <div class="gallery__thumbs">
                <?php for ($i = 1; $i <= 3; $i++): ?>
                <button type="button" class="gallery__thumb" data-label="<?= e($views[$i]) ?>" aria-label="Show <?= e(strtolower($views[$i])) ?>">
                    <span class="gallery__frame"><?= $media($i, false) ?></span>
                </button>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Info + purchase -->
        <div class="product-info">

            <div class="product-summary">
                <p class="eyebrow"><?= e($collection) ?> / <?= e($product['category']) ?></p>
                <h1 class="product-title"><?= e($product['name']) ?></h1>
                <p class="product-price">
                    <?php if (!empty($product['sale_price'])): ?>
                        <?= e(format_price((float) $product['sale_price'])) ?>
                        <s class="product-card__was"><?= e(format_price((float) $product['price'])) ?></s>
                    <?php else: ?>
                        <?= e(format_price((float) $product['price'])) ?>
                    <?php endif; ?>
                </p>
                <p class="product-description"><?= e($description) ?></p>
            </div>

            <form class="purchase" id="purchase" method="post" action="<?= product_url($product['slug']) ?>"
                  data-product-form data-api-form="add-to-bag" data-slug="<?= e($product['slug']) ?>" data-cart-url="<?= url('cart.php') ?>" data-stock="<?= e(json_encode($stock)) ?>">
                <?= csrf_field() ?>

                <fieldset class="option-group">
                    <legend>Color &middot; <span data-selected-color><?= e($color) ?></span></legend>
                    <div class="swatch-grid">
                        <?php foreach ($colors as $c): ?>
                        <label class="swatch">
                            <input type="radio" name="color" value="<?= e($c) ?>"<?= $c === $color ? ' checked' : '' ?>>
                            <span class="swatch__card" style="--swatch: <?= e(color_swatch($c)) ?>">
                                <span class="swatch__dot"></span>
                                <span class="swatch__name"><?= e($c) ?></span>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <fieldset class="option-group">
                    <legend>Size &middot; <span data-selected-size><?= e($size !== '' ? $size : 'Select') ?></span></legend>
                    <div class="size-options">
                        <?php foreach ($allSizes as $s): ?>
                            <?php $sold = $qtyFor($color, $s) < 1; ?>
                        <label class="size-option<?= $sold ? ' is-soldout' : '' ?>">
                            <input type="radio" name="size" value="<?= e($s) ?>"<?= $s === $size ? ' checked' : '' ?><?= $sold ? ' disabled' : '' ?>>
                            <span data-size-label="<?= e($s) ?>"><?= e($sold ? $s . ' · Sold out' : $s) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="form-note" data-soldout-note<?= $soldOutNote === '' ? ' hidden' : '' ?>><?= e($soldOutNote) ?></p>
                </fieldset>

                <p class="stock-badge<?= $available > 0 ? '' : ' stock-badge--out' ?>" data-badge><?= $available > 0 ? 'In stock' : 'Sold out' ?></p>
                <p class="availability" data-availability><?= e($availability) ?></p>

                <div class="buy-row">
                    <div class="qty">
                        <button type="button" class="qty__btn" data-qty-minus aria-label="Decrease quantity">&minus;</button>
                        <input class="qty__input" type="number" name="qty" value="1" min="1" max="<?= max(1, $available) ?>" inputmode="numeric" aria-label="Quantity" data-qty>
                        <button type="button" class="qty__btn" data-qty-plus aria-label="Increase quantity">+</button>
                    </div>
                    <button type="submit" class="btn btn--primary" data-add<?= $available > 0 ? '' : ' disabled' ?>>Add to bag</button>
                </div>

                <p class="delivery-note">Cash on delivery &middot; delivery fee <?= e(format_price(delivery_fee())) ?></p>

                <?php if ($formError): ?>
                    <p class="form-status form-status--error" role="alert"><?= e($formError) ?></p>
                <?php endif; ?>
            </form>

            <section class="product-details">
                <h2><?= $details !== '' ? 'Details &amp; care' : 'Size &amp; delivery' ?></h2>
                <?php if ($details !== ''): ?>
                <p><?= e($details) ?></p>
                <?php endif; ?>
                <div class="product-details__links">
                    <a class="btn btn--ghost" href="<?= url('size-guide.php') ?>">Size guide &middot; <?= e($allSizes ? $allSizes[0] . '–' . end($allSizes) : '') ?></a>
                    <a class="btn btn--ghost" href="<?= url('delivery-policy.php') ?>">Delivery &amp; returns policies</a>
                </div>
            </section>
        </div>
    </div>

    <!-- Related -->
    <?php if ($related): ?>
    <section class="section">
        <h2>Complete your everyday edit.</h2>
        <div class="product-grid product-grid--related">
            <?php foreach ($related as $product): ?>
                <?php include __DIR__ . '/components/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>

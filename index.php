<?php
$pageDescription = 'Everyday clothing. Considered essentials for the way you move.';
require_once __DIR__ . '/components/header.php';

// Products come from the database (all_products() in config/app.php)
$products   = all_products();
$featured   = array_slice($products, 0, 6);
$essentials = array_slice($products, 0, 3);

$chips = [
    'All clothing' => 'shop.php',
    'T-shirts'     => 'shop.php?category=T-shirts',
    'Shirts'       => 'shop.php?category=Shirts',
    'Trousers'     => 'shop.php?category=Trousers',
    'Layers'       => 'shop.php?category=Layers',
];

$promos = [
    [
        'eyebrow' => 'The linen edit',
        'title'   => 'An easier kind of dressing.',
        'caption' => 'Linen collection / editorial styling',
        'href'    => 'collections.php?collection=linen',
    ],
    [
        'eyebrow' => 'Essential cotton',
        'title'   => 'The foundation of your wardrobe.',
        'caption' => 'Cotton collection / fabric and fit',
        'href'    => 'collections.php?collection=cotton',
    ],
];
?>

<!-- Hero -->
<section class="container hero">
    <div class="hero__copy">
        <p class="eyebrow">The everyday collection</p>
        <h1>Less noise.<br>More you.</h1>
        <p class="hero__lead">Considered essentials for the way you move. Start with a good tee. Make it your own.</p>
        <a class="btn btn--primary" href="<?= url('shop.php') ?>">Explore the collection</a>
    </div>
    <div class="hero__media">
        <?= image_placeholder('Collection campaign', false, 'hero') ?>
    </div>
</section>

<!-- Category chips -->
<section class="container section--compact">
    <nav class="chips" aria-label="Browse by category">
        <?php foreach ($chips as $label => $href): ?>
            <a class="chip" href="<?= url($href) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
</section>

<!-- Everyday, elevated -->
<section class="container section--compact">
    <div class="section-head">
        <h2>Your everyday, elevated.</h2>
        <a class="btn btn--ghost" href="<?= url('shop.php') ?>">Shop all clothing &rarr;</a>
    </div>
    <div class="product-grid">
        <?php foreach ($featured as $product): ?>
            <?php include __DIR__ . '/components/product-card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>

<!-- Promo cards -->
<section class="container section">
    <div class="promo-grid">
        <?php foreach ($promos as $promo): ?>
        <article class="promo-card">
            <?= image_placeholder($promo['caption'], false, 'promo') ?>
            <p class="eyebrow"><?= e($promo['eyebrow']) ?></p>
            <h2 class="card-title"><?= e($promo['title']) ?></h2>
            <a class="btn btn--outline" href="<?= url($promo['href']) ?>">Discover collection</a>
        </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- Essentials -->
<section class="container section">
    <div class="section-head section-head--stacked">
        <h2>Start with the essentials.</h2>
        <p>A small edit of everyday favourites. No extras, just the pieces.</p>
    </div>
    <div class="product-grid">
        <?php foreach ($essentials as $product): ?>
            <?php include __DIR__ . '/components/product-card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>

<!-- Brand strip -->
<section class="container section">
    <div class="brand-strip">
        <h2 class="card-title">Good style starts simply.</h2>
        <p>Meet <?= e(APP_NAME) ?> and the thinking behind our everyday collection.</p>
        <a class="btn btn--ghost" href="<?= url('about.php') ?>">About the brand</a>
    </div>
</section>

<?php require_once __DIR__ . '/components/footer.php'; ?>

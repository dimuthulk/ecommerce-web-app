<?php
require_once __DIR__ . '/config/app.php';

$pageTitle       = 'About';
$pageDescription = 'A wardrobe built around simple, wearable pieces.';
require_once __DIR__ . '/components/header.php';

// Replace this copy with the brand story once the client supplies it
$values = [
    ['Wearable by design', 'An edit of tees, shirts, trousers and everyday layers.'],
    ['Details that matter', 'Clear size and color choices, with stock shown for each variant.'],
    ['Your own rhythm',    'Shop a considered wardrobe without discounts, memberships or distractions.'],
];
?>

<div class="container page-top">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li><a href="<?= url('/') ?>">Home</a></li>
            <li aria-current="page">About</li>
        </ol>
    </nav>

    <section class="about-hero">
        <div class="about-hero__copy">
            <p class="eyebrow">About <?= e(APP_NAME) ?></p>
            <h1>The everyday,<br>considered.</h1>
            <p class="about-hero__lead">We’re building a wardrobe around simple, wearable pieces. Clean silhouettes, an easy palette and room to make them your own.</p>
            <a class="btn btn--primary" href="<?= url('shop.php') ?>">Explore the collection</a>
        </div>
        <div class="about-hero__media">
            <?= image_placeholder('Brand story / studio or collection image', false, 'hero') ?>
        </div>
    </section>

    <section class="about-values" aria-labelledby="values-title">
        <h2 id="values-title">A simpler way to get dressed.</h2>
        <div class="about-values__grid">
            <?php foreach ($values as [$title, $text]): ?>
            <div>
                <h3><?= e($title) ?></h3>
                <p><?= e($text) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>

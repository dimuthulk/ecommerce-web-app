<?php
require_once __DIR__ . '/config/app.php';

$pageTitle       = 'About';
$pageDescription = 'A wardrobe built around simple, wearable pieces. Learn how we work, deliver and handle returns.';
require_once __DIR__ . '/components/header.php';

// Optional brand photo: put it at assets/images/about-hero.webp (or .jpg). Without it a simple brand panel is shown.
$aboutImage = '';
foreach (['about-hero.webp', 'about-hero.jpg', 'about-hero.png'] as $candidate) {
    if (is_file(__DIR__ . '/assets/images/' . $candidate)) {
        $aboutImage = $candidate;
        break;
    }
}

$values = [
    ['Wearable by design',   'An edit of tees, shirts, trousers and everyday layers that work together and wear well, season after season.'],
    ['Details that matter',  'Clear size and colour choices, with stock shown for each variant, so you know what you are getting before you order.'],
    ['Your own rhythm',      'Shop a considered wardrobe without discounts, memberships or distractions. Take your time and choose what you need.'],
];

$steps = [
    ['01', 'Choose',  'Browse by category, pick a colour and a size. Every piece shows what is in stock.'],
    ['02', 'Order',   'Pay online with PayHere or by cash on delivery. Your bag and checkout stay simple.'],
    ['03', 'Receive', 'We pack within 1 to 2 working days and deliver across Sri Lanka. Track every order in your account.'],
    ['04', 'Exchange', 'Not quite right? Return or exchange within 7 days, no fuss.'],
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
            <p class="eyebrow">About <?= e(app_name()) ?></p>
            <h1>The everyday,<br>considered.</h1>
            <p class="about-hero__lead">We’re building a wardrobe around simple, wearable pieces. Clean silhouettes, an easy palette and room to make them your own.</p>
            <a class="btn btn--primary" href="<?= url('shop.php') ?>">Explore the collection</a>
        </div>
        <div class="about-hero__media">
            <?php if ($aboutImage !== ''): ?>
                <img class="about-hero__img" src="<?= e(asset('images/' . $aboutImage)) ?>" alt="<?= e(app_name()) ?> everyday collection">
            <?php else: ?>
                <div class="about-brand" aria-hidden="true">
                    <span class="about-brand__name"><?= e(app_name()) ?></span>
                    <span class="about-brand__tag"><?= e(APP_TAGLINE) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="about-story" aria-labelledby="story-title">
        <h2 id="story-title">Why we started.</h2>
        <div class="about-story__text">
            <p>Most wardrobes are full of things we rarely wear and short of the few we reach for every day. We wanted to change that by making a small range of clothing that is easy to put together and comfortable from morning to night.</p>
            <p>Each piece starts with a good fabric and a fit that works on real people. We keep the range focused, so a tee, a shirt or a pair of trousers is there because it earns its place.</p>
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

    <section class="about-steps" aria-labelledby="steps-title">
        <h2 id="steps-title">How it works.</h2>
        <ol class="about-steps__grid">
            <?php foreach ($steps as [$num, $title, $text]): ?>
            <li class="about-step">
                <span class="about-step__num" aria-hidden="true"><?= e($num) ?></span>
                <h3><?= e($title) ?></h3>
                <p><?= e($text) ?></p>
            </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section class="about-cta">
        <h2 class="card-title">Questions about an order or a fit?</h2>
        <p>We read every message and reply within 2 working days.</p>
        <div class="about-cta__actions">
            <a class="btn btn--primary" href="<?= url('contact.php') ?>">Contact us</a>
            <a class="btn btn--outline" href="<?= url('returns-policy.php') ?>">Returns policy</a>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>
<?php
require_once __DIR__ . '/config/app.php';

$pageTitle       = 'Size guide';
$pageDescription = 'How to measure yourself and find your size, from XS to XL.';
require_once __DIR__ . '/components/header.php';

// Body measurements in centimetres
$chart = [
    ['XS', '84 - 89',   '70 - 75'],
    ['S',  '90 - 95',   '76 - 81'],
    ['M',  '96 - 101',  '82 - 87'],
    ['L',  '102 - 107', '88 - 93'],
    ['XL', '108 - 115', '94 - 101'],
];
?>

<div class="container page-top">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li><a href="<?= url('/') ?>">Home</a></li>
            <li aria-current="page">Size guide</li>
        </ol>
    </nav>

    <header class="listing-head">
        <h1>Size guide</h1>
        <p>Find the size that fits you best, from XS to XL.</p>
    </header>

    <div class="size-guide">
        <section class="card policy-section">
            <h2>Body measurements</h2>
            <p>These are measurements of your body, not of the garment. All values are in centimetres.</p>
            <div class="size-table-wrap">
                <table class="size-table">
                    <caption class="sr-only">Size chart in centimetres</caption>
                    <thead>
                        <tr><th scope="col">Size</th><th scope="col">Chest</th><th scope="col">Waist</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($chart as [$sizeName, $chest, $waist]): ?>
                        <tr><th scope="row"><?= e($sizeName) ?></th><td><?= e($chest) ?></td><td><?= e($waist) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card policy-section">
            <h2>How to measure</h2>
            <ol class="size-steps">
                <li><strong>Chest.</strong> Wrap the tape around the fullest part of your chest, under your arms, keeping it level.</li>
                <li><strong>Waist.</strong> Measure around the narrowest part of your waist, usually just above the belly button.</li>
                <li><strong>Keep it relaxed.</strong> Stand naturally and keep the tape snug, not tight.</li>
            </ol>
        </section>

        <section class="card policy-section">
            <h2>Between two sizes?</h2>
            <p>Choose the larger size for a looser fit, or the smaller size for a closer fit. Not quite right? You can request a return or exchange within 7 days of receiving your order.</p>
            <div class="size-guide__actions">
                <a class="btn btn--primary" href="<?= url('shop.php') ?>">Shop clothing</a>
                <a class="btn btn--outline" href="<?= url('returns-policy.php') ?>">Returns policy</a>
                <a class="btn btn--ghost" href="<?= url('contact.php') ?>">Ask about a fit</a>
            </div>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>

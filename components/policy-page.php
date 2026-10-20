<?php
/**
 * Renders one policy page from data/policies.php.
 * @var string $policyKey e.g. 'delivery-policy'
 */
require_once __DIR__ . '/../config/app.php';

$policies = require __DIR__ . '/../data/policies.php';
$policy   = $policies[$policyKey] ?? null;
if ($policy === null) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

$pageTitle = $policy['title'];
require_once __DIR__ . '/header.php';

$contactLine = trim(implode(' · ', array_filter([STORE_SUPPORT_EMAIL, STORE_SUPPORT_PHONE])));
?>

<div class="container page-top">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li><a href="<?= url('/') ?>">Home</a></li>
            <li aria-current="page"><?= e($policy['title']) ?></li>
        </ol>
    </nav>

    <header class="listing-head">
        <h1><?= e($policy['title']) ?></h1>
        <?php if ($policy['draft']): ?>
            <p>Structured policy placeholder &middot; not an approved legal document</p>
        <?php else: ?>
            <p>Effective <?= e($policy['effective']) ?></p>
        <?php endif; ?>
    </header>

    <?php if ($policy['draft']): ?>
    <div class="notice policy-notice" role="note">
        <p class="notice__title">Draft &middot; awaiting client approval</p>
        <p>The client must supply and approve this policy before publication. Bracketed sections below are content placeholders, not legal advice or live store commitments.</p>
    </div>
    <?php endif; ?>

    <div class="policy-layout">

        <nav class="policy-nav" aria-labelledby="policy-nav-title">
            <h2 id="policy-nav-title">In this policy</h2>
            <ul>
                <?php foreach ($policy['sections'] as $section): ?>
                <li><a class="btn btn--ghost" href="#<?= e(slugify($section['title'])) ?>"><?= e($section['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <p class="policy-meta">Effective date: <?= e($policy['effective']) ?><br>Version: <?= e($policy['version']) ?></p>
        </nav>

        <div class="policy-sections">
            <?php foreach ($policy['sections'] as $section): ?>
            <section class="card policy-section" id="<?= e(slugify($section['title'])) ?>">
                <h2><?= e($section['title']) ?></h2>
                <?php if (!empty($section['paragraphs'])): ?>
                    <?php foreach ($section['paragraphs'] as $paragraph): ?>
                        <p><?= e($paragraph) ?></p>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p><?= e($section['placeholder']) ?></p>
                    <span class="policy-flag">Client content required</span>
                <?php endif; ?>
            </section>
            <?php endforeach; ?>

            <section class="card policy-section">
                <h2>Questions about this policy</h2>
                <p><?= $contactLine !== '' ? e($contactLine) : ($policy['draft'] ? '[Approved support email and store contact details]' : 'Send the store a message and we will get back to you.') ?></p>
                <a class="btn btn--outline policy-button" href="<?= url('contact.php') ?>">Contact the store</a>
            </section>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

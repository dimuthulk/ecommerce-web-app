<?php
require_once __DIR__ . '/config/app.php';
http_response_code(404);

$pageTitle = 'Page not found';
require_once __DIR__ . '/components/header.php';
?>

<div class="container page-top">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li><a href="<?= url('/') ?>">Home</a></li>
            <li aria-current="page">404</li>
        </ol>
    </nav>

    <section class="not-found">
        <p class="not-found__code" aria-hidden="true">404</p>
        <h1>Not part of the collection.</h1>
        <p>This page has moved or doesn’t exist. Let’s find something that does.</p>

        <div class="not-found__actions">
            <a class="btn btn--primary" href="<?= url('/') ?>">Back to home</a>
            <a class="btn btn--outline" href="<?= url('shop.php') ?>">Shop clothing</a>
        </div>
    </section>

    <form class="field not-found__search" method="get" action="<?= url('shop.php') ?>" role="search">
        <label for="q404">Search the store</label>
        <input class="input" id="q404" name="q" type="search" placeholder="Search clothing" autocomplete="off">
        <button type="submit" class="sr-only">Search</button>
    </form>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>

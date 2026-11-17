<?php
/**
 * Opens the page: <html>, <head>, site header and <main>.
 * footer.php closes <main>, adds the footer and closes <body>/<html>.
 *
 * Usage in a page:
 *   $pageTitle = 'Shop';                       // optional
 *   $pageDescription = 'Everyday clothing.';   // optional
 *   require_once __DIR__ . '/components/header.php';
 */
require_once __DIR__ . '/../config/app.php';

$documentTitle = isset($pageTitle) ? $pageTitle . ' | ' . app_name() : app_name();
$currentPage   = basename($_SERVER['SCRIPT_NAME']);
$accountUrl    = url(is_logged_in() ? 'account.php' : 'login.php');

$navLinks = [
    'Shop'        => 'shop.php',
    'About'       => 'about.php',
    'Contact'     => 'contact.php',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($documentTitle) ?></title>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="api-base" content="<?= e(url('api')) ?>">
    <?php if (!empty($noReferrer)): ?>
    <meta name="referrer" content="no-referrer">
    <?php endif; ?>
    <?php if (!empty($pageDescription)): ?>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>

    <header class="site-header">
        <a class="site-logo" href="<?= url('/') ?>"><?= e(app_name()) ?></a>

        <nav class="site-nav" id="site-nav" aria-label="Main">
            <ul>
                <?php foreach ($navLinks as $label => $file): ?>
                <li>
                    <a class="btn btn--ghost" href="<?= url($file) ?>"<?= $currentPage === $file ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                </li>
                <?php endforeach; ?>
                <li class="nav-mobile-only">
                    <a class="btn btn--ghost" href="<?= url('shop.php') ?>#search">Search</a>
                </li>
                <li class="nav-mobile-only">
                    <a class="btn btn--ghost" href="<?= $accountUrl ?>">Account</a>
                </li>
            </ul>
        </nav>

        <div class="header-actions">
            <form class="header-search" action="<?= url('shop.php') ?>" method="get" role="search">
                <svg class="hdr-icon" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 15 15" aria-hidden="true"><path d="M0 0h15v15H0z" fill="none" /><path fill="currentColor" d="M6 0a6.005 6.005 0 0 1 4.89 9.48l3.82 3.81c.39.39.39 1.03 0 1.42s-1.03.39-1.42 0l-3.81-3.82A6.005 6.005 0 0 1 0 6c0-3.31 2.69-6 6-6m0 2C3.79 2 2 3.79 2 6s1.79 4 4 4s4-1.79 4-4s-1.79-4-4-4" /></svg>
                <input type="search" name="q" placeholder="Search clothing" aria-label="Search clothing" autocomplete="off">
            </form>
            <a class="btn btn--outline icon-btn header-account" href="<?= $accountUrl ?>" title="Account">
                <svg class="hdr-icon" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" aria-hidden="true"><path d="M0 0h24v24H0z" fill="none" /><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10s10-4.48 10-10S17.52 2 12 2M7.35 18.5C8.66 17.56 10.26 17 12 17s3.34.56 4.65 1.5c-1.31.94-2.91 1.5-4.65 1.5s-3.34-.56-4.65-1.5m10.79-1.38a9.95 9.95 0 0 0-12.28 0A7.96 7.96 0 0 1 4 12c0-4.42 3.58-8 8-8s8 3.58 8 8c0 1.95-.7 3.73-1.86 5.12" /><path fill="currentColor" d="M12 6c-1.93 0-3.5 1.57-3.5 3.5S10.07 13 12 13s3.5-1.57 3.5-3.5S13.93 6 12 6m0 5c-.83 0-1.5-.67-1.5-1.5S11.17 8 12 8s1.5.67 1.5 1.5S12.83 11 12 11" /></svg>
                <span class="sr-only">Account</span>
            </a>
            <a class="btn btn--outline icon-btn header-bag" href="<?= url('cart.php') ?>" title="Bag">
                <svg class="hdr-icon" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" aria-hidden="true"><path d="M0 0h24v24H0z" fill="none" /><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M3.977 9.84A2 2 0 0 1 5.971 8h12.058a2 2 0 0 1 1.994 1.84l.803 10A2 2 0 0 1 18.833 22H5.167a2 2 0 0 1-1.993-2.16z" /><path d="M16 11V6a4 4 0 0 0-4-4v0a4 4 0 0 0-4 4v5" /></g></svg>
                <span class="sr-only">Bag</span>
                <span class="bag-badge" data-cart-count<?= cart_count() < 1 ? ' hidden' : '' ?>><?= cart_count() ?></span>
            </a>
            <button type="button" class="btn btn--ghost nav-toggle" aria-expanded="false" aria-controls="site-nav" aria-label="Menu">
                <svg class="icon-open" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                <svg class="icon-close" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
    </header>

    <main id="main" class="site-main">
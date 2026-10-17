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

$documentTitle = isset($pageTitle) ? $pageTitle . ' | ' . APP_NAME : APP_NAME;
$currentPage   = basename($_SERVER['SCRIPT_NAME']);
$accountUrl    = url(is_logged_in() ? 'account.php' : 'login.php');

$navLinks = [
    'Shop'        => 'shop.php',
    'Collections' => 'collections.php',
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
        <a class="site-logo" href="<?= url('/') ?>"><?= e(APP_NAME) ?></a>

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
            <a class="btn btn--outline header-search" href="<?= url('shop.php') ?>#search">Search</a>
            <a class="btn btn--ghost header-account" href="<?= $accountUrl ?>">Account</a>
            <a class="btn btn--outline header-bag" href="<?= url('cart.php') ?>">Bag <span class="bag-count"><span class="bag-paren">(</span><span data-cart-count><?= cart_count() ?></span><span class="bag-paren">)</span></span></a>
            <button type="button" class="btn btn--ghost nav-toggle" aria-expanded="false" aria-controls="site-nav" aria-label="Menu">
                <svg class="icon-open" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                <svg class="icon-close" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
    </header>

    <main id="main" class="site-main">

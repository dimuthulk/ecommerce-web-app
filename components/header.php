<?php
require_once __DIR__ . '/../config/app.php';

$documentTitle = isset($pageTitle) ? $pageTitle . ' | ' . APP_NAME : APP_NAME;
$currentPage   = basename($_SERVER['SCRIPT_NAME']);

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
                    <a class="btn btn--ghost" href="<?= url('login.php') ?>">Account</a>
                </li>
            </ul>
        </nav>

        <div class="header-actions">
            <button type="button" class="btn btn--outline header-search">Search</button>
            <a class="btn btn--ghost header-account" href="<?= url('login.php') ?>">Account</a>
            <a class="btn btn--outline" href="<?= url('cart.php') ?>">Bag (<span data-cart-count><?= cart_count() ?></span>)</a>
            <button type="button" class="btn btn--outline nav-toggle" aria-expanded="false" aria-controls="site-nav">Menu</button>
        </div>
    </header>

    <main id="main" class="site-main">
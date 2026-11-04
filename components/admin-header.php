<?php
/**
 * Opens an admin page: <head>, top bar, sidebar (desktop) + drawer (mobile), <main>.
 * @var string $adminPage  key of the active menu item (dashboard, products, ...)
 * @var string $adminTitle page title
 * @var bool   $adminBare  true on the login page (no menu)
 */
require_once __DIR__ . '/../config/app.php';

$adminBare = $adminBare ?? false;
$menu = [
    'dashboard'  => ['Dashboard',     'admin/'],
    'products'   => ['Products',      'admin/products.php'],
    'variants'   => ['Variant stock', 'admin/variants.php'],
    'categories' => ['Categories',    'admin/categories.php'],
    'orders'     => ['Orders',        'admin/orders.php'],
    'customers'  => ['Customers',     'admin/customers.php'],
    'reports'    => ['Reports',       'admin/reports.php'],
    'settings'   => ['Settings',      'admin/settings.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="api-base" content="<?= e(url('api')) ?>">
    <meta name="admin-base" content="<?= e(url('admin/')) ?>">
    <meta name="store-currency" content="<?= e(currency_code()) ?>">
    <title><?= e($adminTitle ?? 'Admin') ?> | <?= e(app_name()) ?> admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body class="admin" data-admin-page="<?= e($adminScript ?? $adminPage ?? '') ?>">
    <header class="admin-header">
        <a class="admin-brand" href="<?= url('admin/') ?>">
            <span class="admin-brand__name"><?= e(app_name()) ?></span>
            <span class="admin-brand__sub">Store administration</span>
        </a>
        <?php if (!$adminBare): ?>
        <div class="admin-header__actions">
            <button type="button" class="btn btn--outline" data-signout>Admin &middot; Sign out</button>
            <button type="button" class="btn btn--outline admin-menu-button" data-drawer-open aria-label="Open menu" aria-expanded="false" aria-controls="admin-drawer">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        </div>
        <?php endif; ?>
    </header>

    <?php if (!$adminBare): ?>
    <div class="admin-drawer" id="admin-drawer" role="dialog" aria-modal="true" aria-label="Admin menu" hidden>
        <div class="admin-drawer__top">
            <span class="admin-brand__name"><?= e(app_name()) ?></span>
            <button type="button" class="btn btn--outline" data-drawer-close aria-label="Close menu">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
        <p class="admin-drawer__label">Secure store admin</p>
        <nav aria-label="Admin">
            <?php foreach ($menu as $key => [$label, $path]): ?>
                <a class="btn <?= ($adminPage ?? '') === $key ? 'btn--primary' : 'btn--outline' ?>" href="<?= url($path) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <p class="admin-drawer__who">Signed in as Administrator</p>
        <button type="button" class="btn btn--ghost" data-signout>Sign out</button>
        <p class="admin-drawer__credit">Website development by Gdoop Platforms</p>
    </div>
    <?php endif; ?>

    <div class="admin-shell<?= $adminBare ? ' admin-shell--bare' : '' ?>">
        <?php if (!$adminBare): ?>
        <aside class="admin-sidebar">
            <nav aria-label="Admin">
                <ul>
                    <?php foreach ($menu as $key => [$label, $path]): ?>
                    <li><a class="admin-nav-link<?= ($adminPage ?? '') === $key ? ' is-active' : '' ?>" href="<?= url($path) ?>"<?= ($adminPage ?? '') === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <p class="admin-sidebar__note">Secure admin workspace</p>
        </aside>
        <?php endif; ?>

        <main class="admin-main" id="main">

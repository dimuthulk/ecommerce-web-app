<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../src/auth.php';
$admin = admin_page_guard();
$adminPage  = 'categories';
$adminTitle = 'Categories';
require __DIR__ . '/../components/admin-header.php';
?>
<p class="admin-crumbs">Admin / Categories</p>
<h1 class="admin-title">Categories</h1>
<p class="admin-sub">Store data &middot; <?= e(date('j F Y')) ?></p>

<div class="admin-toolbar"><div class="field admin-toolbar__grow"><label for="q">Search categories</label><input class="input" id="q" type="search" placeholder="Category name or slug" autocomplete="off"></div></div>
<div class="admin-split">
    <div><div id="results" aria-live="polite"><p class="admin-loading">Loading&hellip;</p></div><p class="admin-note" id="note"></p></div>
    <section class="card admin-panel" id="editor"></section>
</div>

<?php require __DIR__ . '/../components/admin-footer.php'; ?>

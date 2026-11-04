<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../src/auth.php';
$admin = admin_page_guard();
$adminPage  = 'products';
$adminTitle = 'Products catalogue';
require __DIR__ . '/../components/admin-header.php';
?>
<p class="admin-crumbs">Admin / Products catalogue</p>
<h1 class="admin-title">Products catalogue</h1>
<p class="admin-sub">Store data &middot; <?= e(date('j F Y')) ?></p>

<div class="admin-toolbar">
    <div class="field admin-toolbar__grow"><label for="q">Search products</label><input class="input" id="q" type="search" placeholder="Product name or SKU" autocomplete="off"></div>
    <div class="field"><label for="category">Category</label><select class="select" id="category"><option value="">All categories</option></select></div>
    <a class="btn btn--primary" href="<?= url('admin/product.php') ?>">Create product</a>
</div>
<p class="admin-summary" id="summary"></p>
<div id="results" aria-live="polite"><p class="admin-loading">Loading&hellip;</p></div>
<div class="admin-pager" id="pager"></div>

<?php require __DIR__ . '/../components/admin-footer.php'; ?>

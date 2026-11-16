<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../src/auth.php';
$admin = admin_page_guard();
$adminPage  = 'variants';
$adminTitle = 'Variant stock';
require __DIR__ . '/../components/admin-header.php';
?>
<p class="admin-crumbs">Admin / Variant stock</p>
<h1 class="admin-title">Variant stock</h1>
<p class="admin-sub">Store data &middot; <?= e(date('j F Y')) ?></p>

<div id="stock-status" aria-live="polite"></div>

<div class="admin-toolbar">
    <div class="field admin-toolbar__grow"><label for="q">Search product or variant</label><input class="input" id="q" type="search" placeholder="Product, SKU, color or size" autocomplete="off"></div>
    <div class="field"><label for="status">Stock status</label>
        <select class="select" id="status"><option value="all">All stock levels</option><option value="in">In stock</option><option value="low">Low stock</option><option value="out">Out of stock</option></select></div>
</div>
<div id="results" aria-live="polite"><p class="admin-loading">Loading&hellip;</p></div>
<div class="admin-pager" id="pager"></div>
<section class="card admin-panel" id="editor" hidden></section>
<p class="admin-note" id="note"></p>

<?php require __DIR__ . '/../components/admin-footer.php'; ?>

<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../src/auth.php';
$admin = admin_page_guard();
$adminPage  = 'customers';
$adminTitle = 'Customers';
require __DIR__ . '/../components/admin-header.php';
?>
<p class="admin-crumbs">Admin / Customers</p>
<h1 class="admin-title">Customers</h1>
<p class="admin-sub">Store data &middot; <?= e(date('j F Y')) ?></p>

<div class="admin-toolbar"><div class="field admin-toolbar__grow"><label for="q">Search customers</label><input class="input" id="q" type="search" placeholder="Name or email address" autocomplete="off"></div></div>
<p class="admin-summary" id="summary"></p>
<div id="results" aria-live="polite"><p class="admin-loading">Loading&hellip;</p></div>
<div class="admin-pager" id="pager"></div>
<div class="admin-actions" id="customer-open"></div>
<p class="admin-note">Customer information is restricted to authenticated administrators. Access and retention must follow the approved privacy policy.</p>

<?php require __DIR__ . '/../components/admin-footer.php'; ?>

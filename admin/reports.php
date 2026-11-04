<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../src/auth.php';
$admin = admin_page_guard();
$adminPage  = 'reports';
$adminTitle = 'Reports';
require __DIR__ . '/../components/admin-header.php';
?>
<p class="admin-crumbs">Admin / Reports</p>
<h1 class="admin-title">Reports</h1>
<p class="admin-sub">Store data &middot; <?= e(date('j F Y')) ?></p>

<div id="app"><p class="admin-loading">Loading&hellip;</p></div>

<?php require __DIR__ . '/../components/admin-footer.php'; ?>

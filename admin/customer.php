<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../src/auth.php';
$admin = admin_page_guard();
$adminPage  = 'customers';
$adminScript = 'customer';
$adminTitle = 'Customer detail';
require __DIR__ . '/../components/admin-header.php';
?>
<p class="admin-crumbs">Admin / Customer detail</p>
<h1 class="admin-title">Customer detail</h1>
<p class="admin-sub">Store data &middot; <?= e(date('j F Y')) ?></p>

<?php $customerId = isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0; ?>
<div id="app" data-id="<?= $customerId ?>"><p class="admin-loading">Loading&hellip;</p></div>

<?php require __DIR__ . '/../components/admin-footer.php'; ?>

<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../src/auth.php';
$admin = admin_page_guard();
$adminPage  = 'orders';
$adminScript = 'order';
$adminTitle = 'Order detail';
require __DIR__ . '/../components/admin-header.php';
?>
<p class="admin-crumbs">Admin / Order detail</p>
<h1 class="admin-title">Order detail</h1>
<p class="admin-sub">Store data &middot; <?= e(date('j F Y')) ?></p>

<?php $orderNumber = (isset($_GET['number']) && is_string($_GET['number']) && preg_match('/^[A-Za-z0-9-]{1,30}$/', $_GET['number'])) ? $_GET['number'] : ''; ?>
<div id="app" data-number="<?= e($orderNumber) ?>"><p class="admin-loading">Loading&hellip;</p></div>

<?php require __DIR__ . '/../components/admin-footer.php'; ?>

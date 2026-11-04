<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../src/auth.php';
$admin = admin_page_guard();
$adminPage  = 'dashboard';
$adminTitle = 'Dashboard';
require __DIR__ . '/../components/admin-header.php';
?>
<p class="admin-crumbs">Admin / Dashboard</p>
<h1 class="admin-title">Dashboard</h1>
<p class="admin-sub">Store data &middot; <?= e(date('j F Y')) ?></p>

<div class="field admin-filter"><label for="period">Reporting period</label>
    <select class="select" id="period"><option value="this_month">This month</option><option value="last_30">Last 30 days</option><option value="last_month">Last month</option><option value="all">All time</option></select></div>
<div id="app"><p class="admin-loading">Loading&hellip;</p></div>

<?php require __DIR__ . '/../components/admin-footer.php'; ?>

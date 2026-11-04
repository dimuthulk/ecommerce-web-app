<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../src/auth.php';
$admin = admin_page_guard();
$adminPage  = 'orders';
$adminTitle = 'Orders';
require __DIR__ . '/../components/admin-header.php';
?>
<p class="admin-crumbs">Admin / Orders</p>
<h1 class="admin-title">Orders</h1>
<p class="admin-sub">Store data &middot; <?= e(date('j F Y')) ?></p>

<div class="admin-toolbar">
    <div class="field admin-toolbar__grow"><label for="q">Search order or customer</label><input class="input" id="q" type="search" placeholder="CB-1001 or customer name" autocomplete="off"></div>
    <div class="field"><label for="status">Status</label>
        <select class="select" id="status"><option value="">All statuses</option><option value="pending">Pending</option><option value="confirmed">Confirmed</option><option value="shipped">Shipped</option><option value="delivered">Delivered</option><option value="cancelled">Cancelled</option></select></div>
    <div class="field"><label for="period">Order date</label><select class="select" id="period"><option value="this_month">This month</option><option value="last_30">Last 30 days</option><option value="last_month">Last month</option><option value="all">All time</option></select></div>
</div>
<div class="admin-tabs" id="tabs" role="tablist" aria-label="Order status"></div>
<div id="results" aria-live="polite"><p class="admin-loading">Loading&hellip;</p></div>
<div class="admin-pager" id="pager"></div>
<p class="admin-note">Open an order to review items, delivery details and change its status.</p>

<?php require __DIR__ . '/../components/admin-footer.php'; ?>

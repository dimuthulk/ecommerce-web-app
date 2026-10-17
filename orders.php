<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/orders.php';
header('Cache-Control: no-store');

$user   = require_login();
$orders = orders_for_user((int) $user['id']);

$activeTab = 'orders';
$pageTitle = 'Your orders';
require_once __DIR__ . '/components/header.php';
?>

<div class="container page-top">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li><a href="<?= url('/') ?>">Home</a></li>
            <li aria-current="page">My orders</li>
        </ol>
    </nav>

    <header class="listing-head">
        <h1>Your orders.</h1>
        <p>Review your purchases and the latest recorded status.</p>
    </header>

    <?php include __DIR__ . '/components/account-tabs.php'; ?>

    <?php if (!$orders): ?>

    <div class="empty-state">
        <h2 class="card-title">No orders yet</h2>
        <p>When you place an order while signed in, it will show up here.</p>
        <a class="btn btn--primary" href="<?= url('shop.php') ?>">Start shopping</a>
    </div>

    <?php else: ?>

    <div class="orders-list">
        <?php foreach ($orders as $order): ?>
            <?php
            [$statusLabel, $statusClass] = order_status_badge($order['status']);
            $itemCount = array_sum(array_map(static fn ($i) => (int) $i['quantity'], $order['items']));
            ?>
        <article class="card order-card">
            <div class="order-card__head">
                <h2><?= e($order['order_number']) ?></h2>
                <p class="status-badge <?= $statusClass ?>"><?= e($statusLabel) ?></p>
            </div>
            <p class="order-card__meta"><?= e(date('j F Y', strtotime($order['created_at']))) ?> &middot; <?= e(items_label($itemCount)) ?></p>

            <?php foreach ($order['items'] as $item): ?>
                <?php $code = preg_replace('/-' . preg_quote($item['color'] . '-' . $item['size'], '/') . '$/', '', $item['sku']); ?>
            <div class="order-line">
                <span class="order-line__image"><?= image_placeholder($code, true, 'thumb') ?></span>
                <div>
                    <span class="order-line__name"><?= e($item['product_name']) ?></span>
                    <span class="order-line__meta"><?= e($item['color'] . ' / ' . $item['size']) ?> &middot; Quantity <?= (int) $item['quantity'] ?></span>
                    <span class="order-line__price"><?= e(format_price((float) $item['line_total'])) ?></span>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="order-row order-row--total">
                <span>Order total &middot; <?= $order['payment_method'] === 'cod' ? 'cash on delivery' : e($order['payment_method']) ?></span>
                <strong><?= e(format_price((float) $order['total_amount'])) ?></strong>
            </div>

            <a class="btn btn--outline order-card__button" href="<?= url('orders/' . $order['order_number']) ?>">View order details</a>
            <p class="confirm-note">Status is updated by the store.</p>
        </article>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>

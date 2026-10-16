<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/cart.php';
require_once __DIR__ . '/config/orders.php';

// Only the order that was just placed in this session can be shown
$orderId = (int) ($_SESSION['last_order_id'] ?? 0);
$order   = $orderId > 0 ? find_order($orderId) : null;

if ($order === null) {
    redirect(url('shop.php'));
}

$firstName = strtok($order['customer_name'], ' ') ?: $order['customer_name'];
$placed    = date('j M Y', strtotime($order['created_at']));
$address   = implode(', ', array_filter([$order['ship_line1'], $order['ship_line2']]))
           . ', ' . trim($order['ship_city'] . ' ' . $order['ship_postal_code']);

$paymentLabels = ['cod' => 'Cash on delivery', 'card' => 'Card', 'bank_transfer' => 'Bank transfer', 'payhere' => 'PayHere'];
$paymentLabel  = $paymentLabels[$order['payment_method']] ?? 'Cash on delivery';

$statusClass = match ($order['status']) {
    'pending'                                     => 'status-badge--pending',
    'confirmed', 'processing', 'shipped', 'delivered' => 'status-badge--active',
    default                                       => '',
};

$totals = [
    'items'    => array_sum(array_map(static fn ($i) => (int) $i['quantity'], $order['items'])),
    'subtotal' => (float) $order['subtotal'],
    'delivery' => (float) $order['shipping_fee'],
    'total'    => (float) $order['total_amount'],
];

$pageTitle = 'Order ' . $order['order_number'];
require_once __DIR__ . '/components/header.php';
?>

<div class="container page-top">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li><a href="<?= url('/') ?>">Home</a></li>
            <li aria-current="page">Confirmation</li>
        </ol>
    </nav>

    <header class="confirm-head">
        <p class="eyebrow eyebrow--lg eyebrow--success">Order received</p>
        <h1>Thank you, <?= e($firstName) ?>.</h1>
        <p>Your order <?= e($order['order_number']) ?> has been recorded. Keep this reference for your records.</p>
        <p class="status-badge <?= $statusClass ?>"><?= e(ucfirst($order['status'])) ?></p>
    </header>

    <div class="confirm-layout">

        <section class="card confirm-card">
            <h2>Order <?= e($order['order_number']) ?></h2>

            <div class="order-row">
                <span>Placed</span>
                <span><?= e($placed) ?></span>
            </div>

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

            <div class="order-row">
                <span>Payment</span>
                <span><?= e($paymentLabel) ?></span>
            </div>
        </section>

        <section class="card confirm-card">
            <h2>Delivery details</h2>
            <address class="confirm-address">
                <?= e($order['customer_name']) ?><br>
                <?= e($address) ?><br>
                <?= e($order['customer_phone']) ?>
            </address>
            <?php if (!empty($order['notes'])): ?>
                <p class="confirm-note">Your note: <?= e($order['notes']) ?></p>
            <?php endif; ?>
            <p class="confirm-note">Delivery timing will be confirmed by the store.</p>
        </section>

        <?php if (ORDER_EMAILS_ENABLED): ?>
        <div class="notice" role="status">
            <p class="notice__title">Order email</p>
            <p>A confirmation message will be submitted to your email address. Delivery to your inbox is not guaranteed, so please check your spam folder too.</p>
        </div>
        <?php endif; ?>

        <div class="confirm-summary">
            <?php include __DIR__ . '/components/order-summary.php'; ?>
        </div>

        <div class="confirm-actions">
            <a class="btn btn--primary" href="<?= url('orders/' . $order['order_number']) ?>">View my order</a>
            <a class="btn btn--outline" href="<?= url('shop.php') ?>">Continue shopping</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>

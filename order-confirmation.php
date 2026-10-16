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

    <header class="listing-head">
        <h1>Thank you, <?= e(strtok($order['customer_name'], ' ')) ?>.</h1>
        <p>Your order <strong><?= e($order['order_number']) ?></strong> has been placed. We will contact you to confirm delivery.</p>
    </header>

    <div class="checkout-layout">

        <div class="checkout-main">
            <section class="card form-card">
                <h2>Delivery</h2>
                <p class="form-card__text">
                    <?= e($order['customer_name']) ?><br>
                    <?= e($order['ship_line1']) ?><br>
                    <?php if (!empty($order['ship_line2'])): ?><?= e($order['ship_line2']) ?><br><?php endif; ?>
                    <?= e($order['ship_city']) ?> <?= e($order['ship_postal_code']) ?><br>
                    <?= e($order['customer_phone']) ?> &middot; <?= e($order['customer_email']) ?>
                </p>
                <?php if (!empty($order['notes'])): ?>
                    <p class="form-card__text">Notes: <?= e($order['notes']) ?></p>
                <?php endif; ?>
            </section>

            <section class="card form-card">
                <h2>Payment</h2>
                <p class="form-card__text">Cash on delivery. Please have <?= e(format_price((float) $order['total_amount'])) ?> ready when your order arrives.</p>
            </section>

            <a class="btn btn--outline cart-continue" href="<?= url('shop.php') ?>">Continue shopping</a>
        </div>

        <aside class="checkout-aside">
            <section class="card" aria-labelledby="items-title">
                <h2 class="summary__title" id="items-title"><?= count($order['items']) === 1 ? 'Your item' : 'Your items' ?></h2>
                <ul class="mini-lines">
                    <?php foreach ($order['items'] as $item): ?>
                    <li class="mini-line">
                        <span class="mini-line__image"><?= image_placeholder($item['sku'], true, 'thumb') ?></span>
                        <span class="mini-line__info">
                            <span class="mini-line__name"><?= e($item['product_name']) ?></span>
                            <span class="mini-line__meta"><?= e($item['color'] . ' / ' . $item['size']) ?> &middot; Quantity <?= (int) $item['quantity'] ?></span>
                            <span class="mini-line__price"><?= e(format_price((float) $item['line_total'])) ?></span>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <?php
            $totals = [
                'items'    => array_sum(array_map(static fn ($i) => (int) $i['quantity'], $order['items'])),
                'subtotal' => (float) $order['subtotal'],
                'delivery' => (float) $order['shipping_fee'],
                'total'    => (float) $order['total_amount'],
            ];
            include __DIR__ . '/components/order-summary.php';
            ?>
        </aside>
    </div>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>

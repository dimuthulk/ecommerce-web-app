<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/orders.php';
header('Cache-Control: no-store');

$number = (isset($_GET['number']) && is_string($_GET['number']) && preg_match('/^[A-Za-z0-9-]{1,30}$/', $_GET['number'])) ? $_GET['number'] : '';
$order  = $number !== '' ? find_order_by_number($number) : null;

// Who may look: the signed-in customer who owns it, or the visitor who has just placed it
$mine = $order !== null && (
    (is_logged_in() && (int) $order['user_id'] === (int) $_SESSION['user_id'])
    || (int) ($_SESSION['last_order_id'] ?? 0) === (int) $order['id']
);

if ($order !== null && !$mine && !is_logged_in()) {
    redirect(url('login.php') . '?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/'));
}

if ($order === null || !$mine) {
    http_response_code(404);
    $pageTitle = 'Order not found';
    require_once __DIR__ . '/components/header.php';
    ?>
    <section class="container section">
        <div class="empty-state">
            <h1 class="card-title">Order not found</h1>
            <p>We could not find that order in your account.</p>
            <a class="btn btn--outline" href="<?= url(is_logged_in() ? 'orders.php' : 'shop.php') ?>"><?= is_logged_in() ? 'Back to my orders' : 'Continue shopping' ?></a>
        </div>
    </section>
    <?php
    require_once __DIR__ . '/components/footer.php';
    exit;
}

[$statusLabel, $statusClass] = order_status_badge($order['status']);

$totals = [
    'items'    => array_sum(array_map(static fn ($i) => (int) $i['quantity'], $order['items'])),
    'subtotal' => (float) $order['subtotal'],
    'delivery' => (float) $order['shipping_fee'],
    'total'    => (float) $order['total_amount'],
];

/* Order progress: Pending -> Confirmed -> Shipped -> Delivered (cancelled / refunded are separate) */
$steps  = ['Pending', 'Confirmed', 'Shipped', 'Delivered'];
$stepOf = ['pending' => 0, 'confirmed' => 1, 'processing' => 1, 'shipped' => 2, 'delivered' => 3];
$halted = in_array($order['status'], ['cancelled', 'refunded'], true);
$current = $stepOf[$order['status']] ?? -1;

/* Status history: when it was placed and, once it changed, the latest update */
$statusNotes = [
    'confirmed'  => 'Order confirmed by the store.',
    'processing' => 'Your order is being prepared.',
    'shipped'    => 'Your order is on its way.',
    'delivered'  => 'Order delivered.',
    'cancelled'  => 'Order cancelled.',
    'refunded'   => 'Order refunded.',
];
$history = [['at' => $order['created_at'], 'label' => 'Pending', 'note' => 'Order received; awaiting store confirmation.']];
if ($order['status'] !== 'pending') {
    $history[] = ['at' => $order['updated_at'], 'label' => ucfirst($order['status']), 'note' => $statusNotes[$order['status']] ?? ''];
}
$history = array_reverse($history);      // newest first

$address = implode(', ', array_filter([$order['ship_line1'], $order['ship_line2']]))
         . ', ' . trim($order['ship_city'] . ' ' . $order['ship_postal_code']);

$pageTitle = 'Order ' . $order['order_number'];
require_once __DIR__ . '/components/header.php';
?>

<div class="container page-top">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li><a href="<?= url('/') ?>">Home</a></li>
            <li aria-current="page">Order detail</li>
        </ol>
    </nav>

    <header class="confirm-head">
        <h1 class="confirm-head__title">Order <?= e($order['order_number']) ?></h1>
        <p>Placed <?= e(date('j F Y', strtotime($order['created_at']))) ?> &middot; <?= e($order['customer_name']) ?></p>
        <p class="status-badge <?= $statusClass ?>"><?= e($statusLabel) ?></p>
    </header>

    <section class="card confirm-card order-progress" aria-labelledby="progress-title">
        <h2 id="progress-title">Order progress</h2>
        <?php if ($halted): ?>
            <p class="order-progress__halt">This order was <?= e($order['status']) ?>.</p>
        <?php else: ?>
        <ol class="progress">
            <?php foreach ($steps as $i => $label): ?>
                <?php
                $state = $i < $current ? 'done' : ($i === $current ? 'current' : 'todo');
                $text  = $state === 'done' ? 'done' : ($state === 'current' ? 'current' : 'not yet');
                ?>
            <li class="progress__step progress__step--<?= $state ?>"<?= $state === 'current' ? ' aria-current="step"' : '' ?>><?= e($label) ?> &middot; <?= $text ?></li>
            <?php endforeach; ?>
        </ol>
        <?php endif; ?>
        <p class="confirm-note">Cancelled is an alternate status, not a delivery step.</p>
    </section>

    <div class="confirm-layout confirm-layout--below">

        <section class="card confirm-card" aria-labelledby="items-heading">
            <h2 id="items-heading">Items</h2>
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
        </section>

        <section class="card confirm-card" aria-labelledby="delivery-heading">
            <h2 id="delivery-heading">Delivery &amp; contact</h2>
            <p class="detail-line"><?= e($order['customer_name']) ?> &middot; <?= e($order['customer_email']) ?></p>
            <p class="detail-line"><?= e($address) ?></p>
            <p class="detail-line"><?= e($order['customer_phone']) ?></p>
            <?php if (!empty($order['notes'])): ?>
                <p class="confirm-note">Your note: <?= e($order['notes']) ?></p>
            <?php endif; ?>
            <p class="confirm-note">Contact the store for delivery timing.</p>
        </section>

        <section class="card confirm-card" aria-labelledby="history-heading">
            <h2 id="history-heading">Status history</h2>
            <?php foreach ($history as $entry): ?>
            <div class="history-entry">
                <div class="order-row">
                    <span><?= e(date('j M Y · H:i', strtotime($entry['at']))) ?></span>
                    <span><?= e($entry['label']) ?></span>
                </div>
                <p class="detail-line"><?= e($entry['note']) ?></p>
            </div>
            <?php endforeach; ?>
        </section>

        <div class="confirm-summary">
            <?php include __DIR__ . '/components/order-summary.php'; ?>
        </div>

        <div class="confirm-actions">
            <?php if (is_logged_in()): ?>
                <a class="btn btn--outline" href="<?= url('orders.php') ?>">Back to my orders</a>
            <?php else: ?>
                <a class="btn btn--outline" href="<?= url('shop.php') ?>">Continue shopping</a>
            <?php endif; ?>
            <a class="btn btn--ghost" href="<?= url('contact.php') ?>">Contact the store</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>

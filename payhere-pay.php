<?php
/** Hands the customer over to PayHere: shows a short "redirecting" page that posts the signed payment form. */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/src/orders.php';
require_once __DIR__ . '/src/payhere.php';

$orderId = (int) ($_SESSION['payhere_order_id'] ?? 0);
$order   = $orderId > 0 ? find_order($orderId) : null;

if ($order === null || $order['payment_method'] !== 'payhere' || $order['status'] !== 'pending' || $order['payment_status'] !== 'pending' || !payhere_enabled()) {
    toast('Online payment is not available', 'error', ['text' => 'Please choose a payment method and try again.']);
    redirect(url('checkout.php'));
}

$fields    = payhere_fields($order);
$pageTitle = 'Redirecting to PayHere';
require_once __DIR__ . '/components/header.php';
?>

<div class="container page-top">
    <header class="listing-head">
        <h1>Taking you to PayHere&hellip;</h1>
        <p>Order <?= e($order['order_number']) ?> &middot; <?= e(format_price((float) $order['total_amount'])) ?></p>
    </header>

    <form id="payhere-form" method="post" action="<?= e(payhere_action_url()) ?>">
        <?php foreach ($fields as $name => $value): ?>
            <input type="hidden" name="<?= e($name) ?>" value="<?= e((string) $value) ?>">
        <?php endforeach; ?>
        <noscript><p>JavaScript is off. Press the button to continue to PayHere.</p></noscript>
        <button type="submit" class="btn btn--primary">Continue to PayHere</button>
        <a class="btn btn--ghost" href="<?= url('payhere-cancel.php') ?>">Cancel payment</a>
    </form>
</div>
<script nonce="<?= e(csp_nonce()) ?>">document.getElementById('payhere-form').submit();</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
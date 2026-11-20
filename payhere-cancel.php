<?php
/** The customer cancelled at PayHere: the order is not paid, its items go back in stock and the bag stays as it was. */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/src/orders.php';

$orderId = (int) ($_SESSION['payhere_order_id'] ?? 0);
if ($orderId > 0) {
    unset($_SESSION['payhere_order_id']);
    try {
        order_release_unpaid($orderId, 'failed', 'Payment cancelled by the customer.');
    } catch (Throwable $e) {
        error_log('PayHere cancel failed: ' . $e->getMessage());
    }
}

toast('Payment cancelled', 'error', ['text' => 'Your payment was not completed and you have not been charged. Your bag is still here: try again or choose cash on delivery.']);
redirect(url('checkout.php'));

<?php
/** PayHere sends the customer here after a payment. The paid status itself is set by payhere-notify.php (server to server). */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/src/cart.php';
require_once __DIR__ . '/src/orders.php';

$orderId = (int) ($_SESSION['payhere_order_id'] ?? 0);
$order   = $orderId > 0 ? find_order($orderId) : null;

if ($order === null || $order['payment_method'] !== 'payhere') {
    redirect(url('shop.php'));
}
if ($order['status'] === 'cancelled') {
    unset($_SESSION['payhere_order_id']);
    toast('Payment was not completed', 'error', ['text' => 'Your order was cancelled and your bag is unchanged. Please try again.']);
    redirect(url('checkout.php'));
}

cart_clear();
unset($_SESSION['payhere_order_id']);
$_SESSION['last_order_id'] = $order['id'];
redirect(url('order-confirmation.php'));

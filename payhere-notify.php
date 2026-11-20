<?php
/**
 * PayHere's server-to-server callback (notify_url). Not for browsers.
 * It checks the md5sig, the order, the amount and the currency before it changes anything.
 * status_code: 2 = paid, 0 = pending, -1 = cancelled, -2 = failed, -3 = charged back.
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/src/orders.php';
require_once __DIR__ . '/src/payhere.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

function payhere_notify_end(int $status, string $logMessage = ''): void
{
    if ($logMessage !== '') {
        error_log('PayHere notify: ' . $logMessage);
    }
    http_response_code($status);
    echo $status === 200 ? 'OK' : 'ERROR';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    payhere_notify_end(405);
}
$config = payhere_config();
if ($config === null) {
    payhere_notify_end(503, 'PayHere is not configured');
}

$field = static fn (string $key): string => is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';

$merchantId = $field('merchant_id');
$orderNo    = $field('order_id');
$amount     = $field('payhere_amount');
$currency   = strtoupper($field('payhere_currency'));
$statusCode = $field('status_code');
$signature  = strtoupper($field('md5sig'));

if ($merchantId !== (string) $config['merchant_id'] || $signature === '') {
    payhere_notify_end(400, 'wrong merchant or missing md5sig for ' . $orderNo);
}
if (!hash_equals(payhere_notify_signature($merchantId, $orderNo, $amount, $currency, $statusCode), $signature)) {
    payhere_notify_end(400, 'md5sig does not match for ' . $orderNo);
}

$order = find_order_by_number($orderNo);
if ($order === null || $order['payment_method'] !== 'payhere') {
    payhere_notify_end(404, 'unknown order ' . $orderNo);
}

if ($statusCode === '2') {
    if (payhere_amount($amount) !== payhere_amount($order['total_amount']) || $currency !== currency_code()) {
        payhere_notify_end(400, 'amount or currency does not match order ' . $orderNo);
    }
    order_mark_paid((int) $order['id'], $field('payment_id'));
} elseif ($statusCode === '-1' || $statusCode === '-2') {
    order_release_unpaid(
        (int) $order['id'],
        'failed',
        $statusCode === '-1' ? 'Payment cancelled at PayHere.' : 'Payment failed at PayHere.'
    );
}
// 0 (pending) and -3 (chargeback) change nothing here

payhere_notify_end(200);

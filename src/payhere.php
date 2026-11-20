<?php
/** PayHere gateway helpers: settings, hashes and the fields of the checkout form. */

const PAYHERE_CURRENCIES = ['LKR', 'USD', 'EUR', 'GBP'];      // currencies PayHere accepts

/** Settings from config/payhere.local.php (localhost) or payhere.remote.php (live), or null when not set up. */
function payhere_config(): ?array
{
    static $config = false;

    if ($config === false) {
        $file   = __DIR__ . '/../config/' . (IS_LOCAL ? 'payhere.local.php' : 'payhere.remote.php');
        $loaded = is_file($file) ? require $file : null;
        $config = (is_array($loaded) && !empty($loaded['merchant_id']) && !empty($loaded['merchant_secret'])) ? $loaded : null;
    }
    return $config;
}

/** Is PayHere ready to use (settings present and the store currency is one PayHere accepts)? */
function payhere_enabled(): bool
{
    return payhere_config() !== null && in_array(currency_code(), PAYHERE_CURRENCIES, true);
}

/** 1500 -> "1500.00" (the exact text that goes into the hash) */
function payhere_amount($amount): string
{
    return number_format((float) $amount, 2, '.', '');
}

function payhere_action_url(): string
{
    return !empty(payhere_config()['sandbox'])
        ? 'https://sandbox.payhere.lk/pay/checkout'
        : 'https://www.payhere.lk/pay/checkout';
}

function payhere_secret_hash(): string
{
    return strtoupper(md5((string) payhere_config()['merchant_secret']));
}

/** Hash sent with the payment form: PayHere refuses a form whose hash does not match. */
function payhere_checkout_hash(string $orderNumber, string $amount, string $currency): string
{
    return strtoupper(md5(payhere_config()['merchant_id'] . $orderNumber . $amount . $currency . payhere_secret_hash()));
}

/** The md5sig PayHere should have sent with its server callback (compare with hash_equals). */
function payhere_notify_signature(string $merchantId, string $orderNumber, string $amount, string $currency, string $statusCode): string
{
    return strtoupper(md5($merchantId . $orderNumber . $amount . $currency . $statusCode . payhere_secret_hash()));
}

/** Every field of the PayHere checkout form for one order (amount and name come from the saved order, never the browser). */
function payhere_fields(array $order): array
{
    require_once __DIR__ . '/auth.php';                         // split_name()

    $amount   = payhere_amount($order['total_amount']);
    $currency = currency_code();
    [$first, $last] = split_name((string) $order['customer_name']);

    return [
        'merchant_id' => (string) payhere_config()['merchant_id'],
        'return_url'  => absolute_url('payhere-return.php'),
        'cancel_url'  => absolute_url('payhere-cancel.php'),
        'notify_url'  => absolute_url('payhere-notify.php'),     // clean URL: .htaccess must not redirect the callback
        'order_id'    => $order['order_number'],
        'items'       => 'Order ' . $order['order_number'],
        'currency'    => $currency,
        'amount'      => $amount,
        'first_name'  => $first,
        'last_name'   => $last !== '' ? $last : '-',
        'email'       => $order['customer_email'],
        'phone'       => $order['customer_phone'],
        'address'     => trim($order['ship_line1'] . ' ' . ($order['ship_line2'] ?? '')),
        'city'        => $order['ship_city'],
        'country'     => 'Sri Lanka',
        'hash'        => payhere_checkout_hash($order['order_number'], $amount, $currency),
    ];
}

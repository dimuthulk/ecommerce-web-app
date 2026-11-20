<?php
/**
 * Input checks shared by the API. Each validate_*() returns [clean values, errors by field name].
 * No errors = the input is fine.
 */

require_once __DIR__ . '/payhere.php';

function v_length(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
}

function v_phone_ok(string $phone): bool
{
    return (bool) preg_match('/^\+?[0-9 ()\-]{7,20}$/', $phone) && strlen(preg_replace('/\D/', '', $phone)) >= 7;
}

function validate_register(array $in): array
{
    $c = ['name' => api_str($in, 'name'), 'email' => api_str($in, 'email')];
    $password = is_string($in['password'] ?? null) ? $in['password'] : '';
    $confirm  = is_string($in['confirm'] ?? null) ? $in['confirm'] : '';
    $e = [];

    if ($c['name'] === '' || v_length($c['name']) > 160) $e['name'] = 'Please enter your full name.';
    if (!filter_var($c['email'], FILTER_VALIDATE_EMAIL) || v_length($c['email']) > 190) {
        $e['email'] = 'Please enter a valid email address.';
    } elseif (user_find_by_email($c['email']) !== null) {
        $e['email'] = 'An account with this email already exists. Try signing in.';
    }
    if ($problem = password_problem($password)) {
        $e['password'] = $problem;
    } elseif ($password !== $confirm) {
        $e['confirm'] = 'The two passwords do not match.';
    }
    if (empty($in['terms'])) $e['terms'] = 'Please accept the privacy policy and store terms.';

    $c['password'] = $password;
    return [$c, $e];
}

function validate_contact(array $in): array
{
    $c = [
        'name'      => api_str($in, 'name'),
        'email'     => api_str($in, 'email'),
        'order_ref' => api_str($in, 'order_ref'),
        'message'   => api_str($in, 'message'),
    ];
    $e = [];
    if ($c['name'] === '' || v_length($c['name']) > 160) $e['name'] = 'Please enter your name.';
    if (!filter_var($c['email'], FILTER_VALIDATE_EMAIL) || v_length($c['email']) > 190) $e['email'] = 'Please enter a valid email address.';
    if ($c['order_ref'] !== '' && !preg_match('/^[A-Za-z0-9-]{1,30}$/', $c['order_ref'])) $e['order_ref'] = 'Order references look like CB-1001.';
    if (v_length($c['message']) < 5 || v_length($c['message']) > 2000) $e['message'] = 'Please write a message (up to 2000 characters).';
    return [$c, $e];
}

/** Profile: name, email (unique among other accounts), phone (optional). */
function validate_profile(array $in, array $user): array
{
    $c = ['name' => api_str($in, 'name'), 'email' => api_str($in, 'email'), 'phone' => api_str($in, 'phone')];
    $e = [];
    if ($c['name'] === '' || v_length($c['name']) > 160) $e['name'] = 'Please enter your full name.';
    if (!filter_var($c['email'], FILTER_VALIDATE_EMAIL) || v_length($c['email']) > 190) {
        $e['email'] = 'Please enter a valid email address.';
    } else {
        $other = user_find_by_email($c['email']);
        if ($other !== null && (int) $other['id'] !== (int) $user['id']) $e['email'] = 'Another account already uses this email.';
    }
    if ($c['phone'] !== '' && !v_phone_ok($c['phone'])) $e['phone'] = 'Please enter a valid phone number.';
    return [$c, $e];
}

function validate_address(array $in): array
{
    $c = ['line1' => api_str($in, 'line1'), 'city' => api_str($in, 'city'), 'postal' => api_str($in, 'postal')];
    $e = [];
    if (v_length($c['line1']) > 190) $e['line1'] = 'Address line 1 is too long.';
    if (v_length($c['city']) > 100)  $e['city']  = 'City is too long.';
    if (v_length($c['postal']) > 20) $e['postal'] = 'Postal code is too long.';
    return [$c, $e];
}

/** Checkout: delivery + contact details, terms, payment method. */
function validate_checkout(array $in): array
{
    $c = [];
    foreach (['name', 'email', 'phone', 'line1', 'line2', 'city', 'postal', 'notes'] as $field) {
        $c[$field] = api_str($in, $field);
    }
    $e = [];
    if ($c['name'] === '' || v_length($c['name']) > 160) $e['name'] = 'Please enter your full name.';
    if (!filter_var($c['email'], FILTER_VALIDATE_EMAIL) || v_length($c['email']) > 190) $e['email'] = 'Please enter a valid email address.';
    if (!v_phone_ok($c['phone'])) $e['phone'] = 'Please enter a valid phone number.';
    if ($c['line1'] === '' || v_length($c['line1']) > 190) $e['line1'] = 'Please enter your address.';
    if (v_length($c['line2']) > 190) $e['line2'] = 'Address line 2 is too long.';
    if ($c['city'] === '' || v_length($c['city']) > 100) $e['city'] = 'Please enter your city.';
    if ($c['postal'] === '' || v_length($c['postal']) > 20) $e['postal'] = 'Please enter your postal code.';
    if (v_length($c['notes']) > 1000) $e['notes'] = 'Order notes are too long (1000 characters max).';
    $method = $in['payment_method'] ?? 'cod';
    if (!in_array($method, payhere_enabled() ? ['cod', 'payhere'] : ['cod'], true)) $e['payment'] = 'Please choose a payment method.';
    if (empty($in['terms'])) $e['terms'] = 'Please accept the store terms and privacy policy.';
    return [$c, $e];
}

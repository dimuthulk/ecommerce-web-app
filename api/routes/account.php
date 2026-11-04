<?php
/** Account endpoints: register, sign in/out, password reset, profile, address, orders. */

/* ---------- Sign in / out ---------- */

// POST /api/auth/register   { name, email, password, confirm, terms, next? }
api_route('POST', '/auth/register', function () {
    $in = api_input();
    [$clean, $errors] = validate_register($in);
    if ($errors) {
        api_fail(422, 'validation_failed', 'Please check the highlighted fields.', $errors);
    }

    try {
        $id = user_create($clean['name'], $clean['email'], $clean['password']);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            api_fail(422, 'validation_failed', 'Please check the highlighted fields.', ['email' => 'An account with this email already exists. Try signing in.']);
        }
        throw $e;
    }
    login_user($id);

    api_ok([
        'user'     => user_public(auth_user(true)),
        'redirect' => safe_next($in['next'] ?? '', url('account.php')),
        'csrf'     => csrf_token(),
    ], 201);
});

// POST /api/auth/login   { email, password, next? }
api_route('POST', '/auth/login', function () {
    $in       = api_input();
    $email    = api_str($in, 'email');
    $password = is_string($in['password'] ?? null) ? $in['password'] : '';

    if ($email === '' || $password === '') {
        api_fail(422, 'validation_failed', 'Please enter your email and password.', ['email' => $email === '' ? 'Please enter your email.' : '', 'password' => $password === '' ? 'Please enter your password.' : '']);
    }

    $result = auth_attempt($email, $password);
    if ($result['user'] === null) {
        if ($result['error'] === 'locked') {
            api_fail(429, 'locked', 'Too many attempts. Please try again in ' . LOGIN_LOCK_MINUTES . ' minutes, or reset your password.');
        }
        api_fail(401, 'invalid_credentials', 'The email or password is incorrect.');
    }

    login_user((int) $result['user']['id']);
    api_ok([
        'user'     => user_public($result['user']),
        'redirect' => safe_next($in['next'] ?? '', url('account.php')),
        'csrf'     => csrf_token(),
    ]);
});

// POST /api/auth/logout
api_route('POST', '/auth/logout', function () {
    logout_user();
    api_ok(['redirect' => url('/')]);
});

/* ---------- Password reset ---------- */

// POST /api/auth/forgot   { email }   (always answers the same, whether the account exists or not)
api_route('POST', '/auth/forgot', function () {
    $email = api_str(api_input(), 'email');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        api_fail(422, 'validation_failed', 'Please enter a valid email address.', ['email' => 'Please enter a valid email address.']);
    }

    $result = request_password_reset($email);
    if (!$result['available']) {
        api_fail(503, 'mail_unavailable', "Reset by email isn't available yet. Please contact the store.");
    }
    api_ok(['sent' => true, 'dev_link' => $result['link']]);     // dev_link only exists on localhost without a mail server
});

// POST /api/auth/reset   { token, password, confirm }
api_route('POST', '/auth/reset', function () {
    $in   = api_input();
    $user = user_by_reset_token(api_str($in, 'token'));
    if ($user === null) {
        api_fail(410, 'invalid_token', 'This link has expired. Request a new one.');
    }

    $password = is_string($in['password'] ?? null) ? $in['password'] : '';
    $confirm  = is_string($in['confirm'] ?? null) ? $in['confirm'] : '';
    if ($problem = password_problem($password)) {
        api_fail(422, 'validation_failed', $problem, ['password' => $problem]);
    }
    if ($password !== $confirm) {
        api_fail(422, 'validation_failed', 'The two passwords do not match.', ['confirm' => 'The two passwords do not match.']);
    }

    password_set((int) $user['id'], $password);
    api_ok(['redirect' => url('login.php')]);
});

/* ---------- Account ---------- */

// GET /api/account
api_route('GET', '/account', function () {
    api_ok(user_public(api_require_user()));
}, 'user');

// PATCH /api/account/profile   { name, email, phone? }
api_route('PATCH', '/account/profile', function () {
    $user = api_require_user();
    [$clean, $errors] = validate_profile(api_input(), $user);
    if ($errors) {
        api_fail(422, 'validation_failed', 'Please check the highlighted fields.', $errors);
    }

    [$first, $last] = split_name($clean['name']);
    db()->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?')
        ->execute([$first, $last, strtolower($clean['email']), $clean['phone'] !== '' ? $clean['phone'] : null, $user['id']]);

    api_ok(user_public(auth_user(true)));
}, 'user');

// PATCH /api/account/address   { line1?, city?, postal? }
api_route('PATCH', '/account/address', function () {
    $user = api_require_user();
    [$clean, $errors] = validate_address(api_input());
    if ($errors) {
        api_fail(422, 'validation_failed', 'Please check the highlighted fields.', $errors);
    }

    $or = static fn (string $v) => $v !== '' ? $v : null;
    db()->prepare('UPDATE users SET address_line1 = ?, city = ?, postal_code = ? WHERE id = ?')
        ->execute([$or($clean['line1']), $or($clean['city']), $or($clean['postal']), $user['id']]);

    api_ok(user_public(auth_user(true)));
}, 'user');

/* ---------- Orders ---------- */

// GET /api/orders   (the signed-in customer's orders, newest first)
api_route('GET', '/orders', function () {
    $user = api_require_user();
    api_ok(array_map(static fn ($o) => order_public($o, false), orders_for_user((int) $user['id'])));
}, 'user');

// GET /api/orders/{number}   (the owner, or the visitor who has just placed it)
api_route('GET', '/orders/{number}', function (array $p) {
    $order = preg_match('/^[A-Za-z0-9-]{1,30}$/', $p['number']) ? find_order_by_number($p['number']) : null;

    $mine = $order !== null && (
        (is_logged_in() && (int) $order['user_id'] === (int) $_SESSION['user_id'])
        || (int) ($_SESSION['last_order_id'] ?? 0) === (int) $order['id']
    );
    if (!$mine) {
        api_fail(is_logged_in() ? 404 : 401, is_logged_in() ? 'not_found' : 'unauthenticated', is_logged_in() ? 'Order not found.' : 'Please sign in first.');
    }
    api_ok(order_public($order));
});

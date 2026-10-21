<?php
/**
 * Accounts: sign in / out, registration, password reset.
 * Passwords are stored only as password_hash() hashes. Reset tokens are stored as SHA-256 hashes.
 */

require_once __DIR__ . '/mail.php';

const LOGIN_MAX_FAILURES = 5;      // wrong passwords before the account is paused
const LOGIN_LOCK_MINUTES = 15;
const RESET_TOKEN_MINUTES = 60;

/* ---------- Who is signed in ---------- */

/** The signed-in user's row (cached for the request), or null. Pass true to reload after an update. */
function auth_user(bool $refresh = false): ?array
{
    static $loaded = false;
    static $user = null;

    if ($refresh || !$loaded) {
        $loaded = true;
        $user   = null;
        $id     = (int) ($_SESSION['user_id'] ?? 0);

        if ($id > 0) {
            $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($user === null) {
                unset($_SESSION['user_id']);
            }
        }
    }
    return $user;
}

/** Send visitors who are not signed in to the login page (and back afterwards). */
function require_login(): array
{
    $user = is_logged_in() ? auth_user() : null;
    if ($user === null) {
        redirect(url('login.php') . '?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/'));
    }
    return $user;
}

/** Only allow "next" targets that stay on this site. */
function safe_next($next, string $fallback): string
{
    $next = is_string($next) ? $next : '';

    if ($next === '' || $next[0] !== '/' || strpos($next, '//') === 0 || strpos($next, '\\') !== false || preg_match('/[\r\n]/', $next)) {
        return $fallback;
    }
    if (BASE_URL !== '' && $next !== BASE_URL && strpos($next, BASE_URL . '/') !== 0) {
        return $fallback;
    }
    return $next;
}

function login_user(int $userId): void
{
    session_regenerate_id(true);          // new session id after signing in (the bag is kept)
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void
{
    unset($_SESSION['user_id'], $_SESSION['last_order_id']);
    session_regenerate_id(true);
}

/* ---------- Users ---------- */

function split_name(string $full): array
{
    $full  = trim(preg_replace('/\s+/', ' ', $full));
    $parts = explode(' ', $full, 2);
    return [$parts[0], $parts[1] ?? ''];
}

function user_display_name(array $user): string
{
    return trim($user['first_name'] . ' ' . $user['last_name']);
}

function user_find_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([strtolower(trim($email))]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function user_create(string $fullName, string $email, string $password): int
{
    [$first, $last] = split_name($fullName);

    $stmt = db()->prepare('INSERT INTO users (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)');
    $stmt->execute([$first, $last, strtolower(trim($email)), password_hash($password, PASSWORD_DEFAULT)]);

    return (int) db()->lastInsertId();
}

/** Error text for a bad password, or null when it is fine. */
function password_problem(string $password, ?string $confirm = null): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (strlen($password) > 72) {
        return 'Password can be up to 72 characters.';
    }
    if ($confirm !== null && $password !== $confirm) {
        return 'The two passwords do not match.';
    }
    return null;
}

/* ---------- Signing in ---------- */

/**
 * Check an e-mail + password.
 * @return array{user:?array, error:string}  error is '' (ok), 'invalid' or 'locked'
 */
function auth_attempt(string $email, string $password): array
{
    $pdo  = db();
    $user = user_find_by_email($email);

    if ($user !== null && !(int) $user['is_active']) {
        $user = null;
    }

    if ($user !== null) {
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE id = ? AND locked_until IS NOT NULL AND locked_until > NOW()');
        $stmt->execute([$user['id']]);
        if ($stmt->fetchColumn()) {
            return ['user' => null, 'error' => 'locked'];
        }
    }

    // Always run a hash check so a missing account takes as long as a wrong password
    static $dummyHash = null;
    $dummyHash ??= password_hash('not-a-real-password', PASSWORD_DEFAULT);
    $valid = password_verify($password, $user['password_hash'] ?? $dummyHash);

    if ($user === null || !$valid) {
        if ($user !== null) {
            // locked_until is set first (it reads the old counter), then the counter moves
            $pdo->prepare(
                'UPDATE users SET
                    locked_until = IF(failed_logins + ? >= ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), locked_until),
                    failed_logins = IF(failed_logins + ? >= ?, 0, failed_logins + ?)
                 WHERE id = ?'
            )->execute([1, LOGIN_MAX_FAILURES, LOGIN_LOCK_MINUTES, 1, LOGIN_MAX_FAILURES, 1, $user['id']]);
        }
        return ['user' => null, 'error' => 'invalid'];
    }

    $pdo->prepare('UPDATE users SET failed_logins = 0, locked_until = NULL WHERE id = ?')->execute([$user['id']]);

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }

    return ['user' => $user, 'error' => ''];
}

/* ---------- Password reset ---------- */

/** Is password reset by e-mail possible right now? (real e-mail on the live site, or local development) */
function password_reset_available(): bool
{
    return MAIL_ENABLED || IS_LOCAL;
}

/**
 * Create a reset link for an e-mail address and send it. Says nothing about whether the account exists.
 * @return array{available:bool, link:?string}  link is only returned on localhost (for testing)
 */
function request_password_reset(string $email): array
{
    if (!password_reset_available()) {
        return ['available' => false, 'link' => null];
    }

    $pdo  = db();
    $user = user_find_by_email($email);
    if ($user === null || !(int) $user['is_active']) {
        return ['available' => true, 'link' => null];
    }

    // At most one new link per minute per account
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE id = ? AND reset_expires_at IS NOT NULL AND reset_expires_at > DATE_ADD(NOW(), INTERVAL ? MINUTE)');
    $stmt->execute([$user['id'], RESET_TOKEN_MINUTES - 1]);
    if ($stmt->fetchColumn()) {
        return ['available' => true, 'link' => null];
    }

    $token = bin2hex(random_bytes(32));
    $pdo->prepare('UPDATE users SET reset_token_hash = ?, reset_expires_at = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?')
        ->execute([hash('sha256', $token), RESET_TOKEN_MINUTES, $user['id']]);

    $link = absolute_url('reset-password.php') . '?token=' . $token;
    send_mail(
        $user['email'],
        'Reset your password',
        "Hello " . $user['first_name'] . ",\n\nUse this link to choose a new password. It can be used once and expires in "
        . RESET_TOKEN_MINUTES . " minutes:\n\n$link\n\nIf you did not ask for this, you can ignore this message.\n\n" . APP_NAME
    );

    return ['available' => true, 'link' => (IS_LOCAL && !MAIL_ENABLED) ? $link : null];
}

/** The user a (still valid) reset token belongs to, or null. */
function user_by_reset_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE reset_token_hash = ? AND reset_expires_at > NOW() AND is_active = 1');
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Save a new password and make the reset link unusable. */
function password_set(int $userId, string $password): void
{
    db()->prepare(
        'UPDATE users SET password_hash = ?, reset_token_hash = NULL, reset_expires_at = NULL, failed_logins = 0, locked_until = NULL WHERE id = ?'
    )->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
}

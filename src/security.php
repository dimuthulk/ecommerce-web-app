<?php
/**
 * Security layer, loaded by config/app.php on every request:
 *  - security headers (CSP with a per-request nonce, clickjacking, MIME sniffing, referrer, HSTS)
 *  - same-origin check for POST requests (extra CSRF defence next to the token)
 *  - rate limiting per visitor address (file based, no database change needed)
 *  - session timeouts (idle + absolute, stricter idle limit inside the admin area)
 */

const CSP_REPORT_ONLY = false;          // true = the browser only reports CSP problems in the console (use to debug, then set false)
const SESSION_IDLE_SECONDS     = 7200;  // signed-in customers: 2 hours without any request
const SESSION_ABSOLUTE_SECONDS = 43200; // ... and never longer than 12 hours after signing in
const ADMIN_IDLE_SECONDS       = 1800;  // admin area: 30 minutes without an admin request

/* ---------- Basics ---------- */

function request_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || strpos((string) ($_SERVER['HTTP_CF_VISITOR'] ?? ''), '"https"') !== false;
}

/** The visitor's address. Only REMOTE_ADDR is trusted; define('TRUSTED_IP_HEADER', 'HTTP_CF_CONNECTING_IP') if the host sits behind a proxy. */
function client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    if (defined('TRUSTED_IP_HEADER') && !empty($_SERVER[TRUSTED_IP_HEADER])) {
        $candidate = trim(explode(',', (string) $_SERVER[TRUSTED_IP_HEADER])[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            $ip = $candidate;
        }
    }
    return $ip;
}

/** One random value per request, used by <script nonce="..."> and the CSP header. */
function csp_nonce(): string
{
    static $nonce = null;
    return $nonce ??= base64_encode(random_bytes(16));
}

/** Where the current request is going: ['api'|'page', path without the site folder and /api prefix]. */
function request_target(): array
{
    $path  = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $isApi = substr(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), -14) === '/api/index.php';
    $base  = BASE_URL . ($isApi ? '/api' : '');
    $path  = $base !== '' && strpos($path, $base) === 0 ? substr($path, strlen($base)) : $path;
    return [$isApi ? 'api' : 'page', '/' . trim($path, '/')];
}

/* ---------- Host header ---------- */

/** A faked Host header ends up in e-mail links (password reset). Refuse anything that is not a plain host name. */
function security_check_host(): void
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if (PHP_SAPI !== 'cli' && $host !== '' && !preg_match('/^(?:[a-z0-9](?:[a-z0-9.-]{0,251}[a-z0-9])?|\[[0-9a-f:.]+\])(?::\d{1,5})?$/i', $host)) {
        security_stop(400, 'bad_request', 'Bad request.');
    }
}

/* ---------- Headers ---------- */

function security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');

    if (request_is_https() && !IS_LOCAL) {
        header('Strict-Transport-Security: max-age=31536000');
    }

    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'nonce-" . csp_nonce() . "'",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com",
        "img-src 'self' data:",
        "connect-src 'self'",
        "form-action 'self' https://www.payhere.lk https://sandbox.payhere.lk",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "object-src 'none'",
    ]);
    header((CSP_REPORT_ONLY ? 'Content-Security-Policy-Report-Only: ' : 'Content-Security-Policy: ') . $csp);

    if (is_logged_in()) {
        header('Cache-Control: private, no-store');       // account pages must not stay in shared caches or the back button
    }
}

/* ---------- Stop the request ---------- */

function security_stop(int $status, string $code, string $message, array $headers = []): void
{
    http_response_code($status);
    foreach ($headers as $line) {
        header($line);
    }
    [$kind] = request_target();
    if ($kind === 'api') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<title>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</title></head>'
           . '<body style="font-family:system-ui,sans-serif;max-width:32rem;margin:15vh auto;padding:0 1rem">'
           . '<h1 style="font-size:1.4rem">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</h1>'
           . '<p><a href="' . htmlspecialchars(BASE_URL . '/', ENT_QUOTES, 'UTF-8') . '">Back to the store</a></p></body></html>';
    }
    exit;
}

/* ---------- Same-origin check (POST only) ---------- */

function security_check_origin(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || PHP_SAPI === 'cli') {
        return;
    }
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'payhere-notify.php') {
        return;                                            // server-to-server callback: no browser, no Origin header
    }

    $site = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
    if ($site === 'cross-site') {
        security_stop(403, 'csrf_failed', 'This request came from another site and was blocked.');
    }

    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '' && $origin !== 'null') {
        $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST) . (($p = parse_url($origin, PHP_URL_PORT)) ? ':' . $p : ''));
        $siteHost   = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $appHost    = APP_URL !== '' ? strtolower((string) parse_url(APP_URL, PHP_URL_HOST) . (($p = parse_url(APP_URL, PHP_URL_PORT)) ? ':' . $p : '')) : '';
        if ($originHost !== $siteHost && ($appHost === '' || $originHost !== $appHost)) {
            security_stop(403, 'csrf_failed', 'This request came from another site and was blocked.');
        }
    }
}

/* ---------- Rate limiting ---------- */

/** [max requests, window in seconds] for the POST requests that need a limit, by bucket name. */
const RATE_LIMITS = [
    'login'    => [10, 600],
    'admin'    => [8, 600],
    'register' => [5, 3600],
    'forgot'   => [5, 3600],
    'reset'    => [10, 3600],
    'checkout' => [10, 3600],
    'contact'  => [10, 3600],
    'write'    => [300, 600],      // every other POST / PATCH / DELETE on the API
];

/** Which bucket (if any) this request counts against. */
function rate_bucket(): ?string
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || PHP_SAPI === 'cli') {
        return null;
    }
    [$kind, $path] = request_target();

    if ($kind === 'api') {
        $map = ['/auth/login' => 'login', '/admin/login' => 'admin', '/auth/register' => 'register', '/auth/forgot' => 'forgot',
                '/auth/reset' => 'reset', '/checkout' => 'checkout', '/contact' => 'contact'];
        return $map[rtrim($path, '/')] ?? 'write';
    }

    $map = ['login.php' => 'login', 'register.php' => 'register', 'forgot-password.php' => 'forgot',
            'reset-password.php' => 'reset', 'checkout.php' => 'checkout', 'contact.php' => 'contact'];
    return $map[basename($_SERVER['SCRIPT_NAME'] ?? '')] ?? null;
}

function rate_dir(): ?string
{
    foreach ([__DIR__ . '/../storage/ratelimit', rtrim(sys_get_temp_dir(), '/\\') . '/shop-ratelimit'] as $dir) {
        if ((is_dir($dir) || @mkdir($dir, 0700, true)) && is_writable($dir)) {
            return $dir;
        }
    }
    return null;
}

/** Count this request; returns 0 when allowed, or the seconds to wait when the limit is used up. */
function rate_hit(string $bucket, string $ip): int
{
    [$max, $window] = RATE_LIMITS[$bucket];
    $dir = rate_dir();
    if ($dir === null) {
        return 0;                                          // cannot store counters: do not lock everybody out
    }

    $file = $dir . '/' . hash('sha256', $bucket . '|' . $ip) . '.json';
    $fh   = @fopen($file, 'c+');
    if ($fh === false) {
        return 0;
    }
    $wait = 0;
    if (flock($fh, LOCK_EX)) {
        $now   = time();
        $hits  = json_decode((string) stream_get_contents($fh), true);
        $hits  = array_values(array_filter(is_array($hits) ? $hits : [], static fn ($t) => is_int($t) && $t > $now - $window));
        if (count($hits) >= $max) {
            $wait = max(1, $hits[0] + $window - $now);
        } else {
            $hits[] = $now;
        }
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($hits));
        fflush($fh);
        flock($fh, LOCK_UN);
    }
    fclose($fh);

    if (random_int(1, 200) === 1) {                        // now and then, remove counters nobody has touched for a day
        foreach (glob($dir . '/*.json') ?: [] as $old) {
            if (@filemtime($old) < time() - 86400) {
                @unlink($old);
            }
        }
    }
    return $wait;
}

function security_rate_limit(): void
{
    $bucket = rate_bucket();
    if ($bucket === null) {
        return;
    }
    $wait = rate_hit($bucket, client_ip());
    if ($wait > 0) {
        security_stop(429, 'rate_limited', 'Too many requests. Please wait a little and try again.', ['Retry-After: ' . $wait]);
    }
}

/* ---------- Session timeouts ---------- */

function security_session_guard(): void
{
    if (empty($_SESSION['user_id'])) {
        unset($_SESSION['signed_in_at'], $_SESSION['last_seen'], $_SESSION['admin_last_seen']);
        return;
    }

    $now = time();
    $_SESSION['signed_in_at'] ??= $now;
    $idle     = $now - (int) ($_SESSION['last_seen'] ?? $now);
    $absolute = $now - (int) $_SESSION['signed_in_at'];

    $expired = $idle > SESSION_IDLE_SECONDS || $absolute > SESSION_ABSOLUTE_SECONDS;

    [, $path] = request_target();
    $inAdmin = (bool) preg_match('#^/admin(/|$)#', $path) || (bool) preg_match('#/admin(/|$)#', (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
    if ($inAdmin) {
        if (isset($_SESSION['admin_last_seen']) && $now - (int) $_SESSION['admin_last_seen'] > ADMIN_IDLE_SECONDS) {
            $expired = true;
        }
    }

    if ($expired) {
        unset($_SESSION['user_id'], $_SESSION['last_order_id'], $_SESSION['signed_in_at'], $_SESSION['last_seen'], $_SESSION['admin_last_seen']);
        session_regenerate_id(true);
        return;
    }

    $_SESSION['last_seen'] = $now;
    if ($inAdmin) {
        $_SESSION['admin_last_seen'] = $now;
    }
}

/* ---------- Run everything ---------- */

function security_boot(): void
{
    security_check_host();
    security_session_guard();
    security_headers();
    security_check_origin();
    security_rate_limit();
}
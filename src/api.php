<?php
/**
 * Tiny JSON API toolkit: routes, responses, input, CSRF and access checks.
 *
 * Every response looks the same:
 *   success  {"ok": true,  "data": ..., "meta": {...}}
 *   failure  {"ok": false, "error": {"code": "validation_failed", "message": "...", "fields": {"email": "..."}}}
 * Unsafe requests (POST, PATCH, PUT, DELETE) must send the CSRF token in the X-CSRF-Token header
 * (get it from GET /api/session or from <meta name="csrf-token"> on every page).
 */

class ApiException extends RuntimeException
{
    public int $status;
    public string $errorCode;
    public array $fields;

    public function __construct(int $status, string $errorCode, string $message, array $fields = [])
    {
        parent::__construct($message);
        $this->status    = $status;
        $this->errorCode = $errorCode;
        $this->fields    = $fields;
    }
}

/** Stop with an error response:  api_fail(404, 'not_found', 'Product not found') */
function api_fail(int $status, string $code, string $message, array $fields = []): void
{
    throw new ApiException($status, $code, $message, $fields);
}

/** Send a success response and stop. */
function api_ok($data = null, int $status = 200, array $meta = []): void
{
    http_response_code($status);
    $out = ['ok' => true, 'data' => $data];
    if ($meta) {
        $out['meta'] = $meta;
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

/* ---------- Input ---------- */

/** The request body as an array (JSON body, or the form fields for multipart uploads). */
function api_input(): array
{
    static $input = null;
    if ($input !== null) {
        return $input;
    }

    $type = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
    if (strpos($type, 'multipart/form-data') !== false || strpos($type, 'application/x-www-form-urlencoded') !== false) {
        return $input = $_POST;
    }

    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 1048576) {
        api_fail(413, 'too_large', 'The request is too large.');
    }
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return $input = [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        api_fail(400, 'invalid_json', 'The request body must be valid JSON.');
    }
    return $input = $decoded;
}

/** A string field from the input (trimmed), or '' when missing / not text. */
function api_str(array $in, string $key): string
{
    return isset($in[$key]) && (is_string($in[$key]) || is_numeric($in[$key])) ? trim((string) $in[$key]) : '';
}

/** A positive integer from the query string, with a default. */
function api_int_query(string $key, int $default, int $min = 1, int $max = 1000): int
{
    $v = isset($_GET[$key]) && is_scalar($_GET[$key]) ? (int) $_GET[$key] : $default;
    return max($min, min($max, $v));
}

/** A list field from the query string (?category[]=a&category[]=b or ?category=a). */
function api_list_query(string $key): array
{
    $values = array_filter((array) ($_GET[$key] ?? []), 'is_scalar');
    $values = array_map(static fn ($v) => trim((string) $v), $values);
    return array_values(array_filter($values, 'strlen'));
}

/* ---------- Access ---------- */

function api_user(): ?array
{
    return is_logged_in() ? auth_user() : null;
}

function api_require_user(): array
{
    $user = api_user();
    if ($user === null) {
        api_fail(401, 'unauthenticated', 'Please sign in first.');
    }
    return $user;
}

function api_require_admin(): array
{
    $user = api_user();
    if ($user === null) {
        api_fail(401, 'unauthenticated', 'Please sign in first.');
    }
    if (($user['role'] ?? '') !== 'admin') {
        api_fail(403, 'forbidden', 'You do not have access to this.');
    }
    return $user;
}

function api_check_csrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || $sent === '' || !hash_equals(csrf_token(), $sent)) {
        api_fail(403, 'csrf_failed', 'Your session expired. Please reload the page and try again.');
    }
}

/* ---------- Routing ---------- */

/**
 * Register a route.  $path may contain {name} parts that are passed to the handler as an array.
 * $access: 'public' | 'user' (signed in) | 'admin' (signed in as administrator)
 */
function api_route(string $method, string $path, callable $handler, string $access = 'public'): void
{
    $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $path) . '/?$#';
    $GLOBALS['api_routes'][] = ['method' => $method, 'regex' => $regex, 'handler' => $handler, 'access' => $access];
}

function api_dispatch(): void
{
    $GLOBALS['api_routes'] = $GLOBALS['api_routes'] ?? [];

    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    // Some hosts block PATCH/DELETE: the browser code sends POST + X-HTTP-Method-Override instead
    if ($method === 'POST' && !empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
        $override = strtoupper((string) $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        if (in_array($override, ['PATCH', 'PUT', 'DELETE'], true)) {
            $method = $override;
        }
    }

    // /ecommerce-web-app/api/products/x  ->  /products/x
    $path   = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $prefix = BASE_URL . '/api';
    $path   = strpos($path, $prefix) === 0 ? substr($path, strlen($prefix)) : $path;
    $path   = '/' . trim($path, '/');
    if ($path === '/index.php') {
        $path = '/';
    }

    $allowed = [];
    foreach ($GLOBALS['api_routes'] as $route) {
        if (!preg_match($route['regex'], $path, $m)) {
            continue;
        }
        if ($route['method'] !== $method) {
            $allowed[] = $route['method'];
            continue;
        }

        if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            api_check_csrf();
        }
        if ($route['access'] === 'user') {
            api_require_user();
        } elseif ($route['access'] === 'admin') {
            api_require_admin();
        }

        $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
        ($route['handler'])(array_map('urldecode', $params));
        api_fail(500, 'no_response', 'The server did not send a response.');
    }

    if ($allowed) {
        header('Allow: ' . implode(', ', array_unique($allowed)));
        api_fail(405, 'method_not_allowed', 'This method is not allowed here.');
    }
    api_fail(404, 'not_found', 'This API address does not exist.');
}

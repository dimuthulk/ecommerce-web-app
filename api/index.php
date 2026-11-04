<?php
/**
 * API front door. The .htaccess sends every  /api/...  address here.
 * The routes themselves live in api/routes/*.php.
 */
ini_set('display_errors', '0');                 // never print PHP errors into JSON

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/cart.php';
require_once __DIR__ . '/../src/orders.php';
require_once __DIR__ . '/../src/products.php';
require_once __DIR__ . '/../src/validators.php';
require_once __DIR__ . '/../src/contact.php';
require_once __DIR__ . '/../src/serializers.php';
require_once __DIR__ . '/../src/admin.php';
require_once __DIR__ . '/../src/api.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

foreach (glob(__DIR__ . '/routes/*.php') as $file) {
    require $file;
}

try {
    api_dispatch();
} catch (ApiException $e) {
    http_response_code($e->status);
    $error = ['code' => $e->errorCode, 'message' => $e->getMessage()];
    if ($e->fields) {
        $error['fields'] = $e->fields;
    }
    echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('API error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'Something went wrong. Please try again.']]);
}

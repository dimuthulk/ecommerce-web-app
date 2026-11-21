<?php
// Same rule as config/app.php: "local" needs a localhost Host header AND a request from this computer.
$serverHost = explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0];
$isLocal = defined('IS_LOCAL')
    ? IS_LOCAL
    : (PHP_SAPI === 'cli')
        || (in_array($serverHost, ['localhost', '127.0.0.1', '::1'], true)
            && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true));

require_once __DIR__ . ($isLocal ? '/database.local.php' : '/database.remote.php');
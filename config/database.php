<?php
// Picks the correct database config automatically:
//   localhost / 127.0.0.1 (or CLI)  -> database.local.php
//   anything else (live server)     -> database.remote.php
// Both files create the PDO connection as $pdo.

$serverHost = explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0];
$isLocal = (PHP_SAPI === 'cli') || in_array($serverHost, ['localhost', '127.0.0.1', '::1'], true);

require_once __DIR__ . ($isLocal ? '/database.local.php' : '/database.remote.php');
<?php
$serverHost = explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0];
$isLocal = (PHP_SAPI === 'cli') || in_array($serverHost, ['localhost', '127.0.0.1', '::1'], true);

require_once __DIR__ . ($isLocal ? '/database.local.php' : '/database.remote.php');
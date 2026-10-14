<?php

const CONFIG_LOCAL  = __DIR__ . '/config/database.local.php';
const CONFIG_REMOTE = __DIR__ . '/config/database.remote.php';
const SQL_FILE      = __DIR__ . '/database/migrations.sql';

// ───── 1. Local or remote? ─────
$isCli   = (PHP_SAPI === 'cli');
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocal = $isCli || in_array(explode(':', $host)[0], ['localhost', '127.0.0.1', '::1'], true);

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

// ───── 2. Load the right config (it creates $pdo) ─────
$configFile = $isLocal ? CONFIG_LOCAL : CONFIG_REMOTE;
if (!file_exists($configFile)) {
    die("Config file not found: $configFile\n");
}
require_once $configFile;

// ───── 3. Remote: secret key required ─────
if (!$isLocal) {
    $key = $_GET['key'] ?? '';
    if (!defined('MIGRATION_SECRET') || !hash_equals(MIGRATION_SECRET, $key)) {
        http_response_code(403);
        die('Forbidden');
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Config file must create a PDO connection named \$pdo.\n");
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo 'Environment : ' . ($isLocal ? 'LOCAL' : 'REMOTE') . "\n";
echo 'Database    : ' . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n\n";

// ───── 4. Read SQL file ─────
if (!file_exists(SQL_FILE)) {
    die("SQL file not found: " . SQL_FILE . "\n");
}
$sql = file_get_contents(SQL_FILE);

// remove "-- comment" lines, then split into statements
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
$statements = array_filter(array_map('trim', explode(';', $sql)));

// ───── 5. Execute one by one ─────
$ok = 0;
try {
    foreach ($statements as $statement) {
        $pdo->exec($statement);
        $ok++;

        if (preg_match('/CREATE TABLE IF NOT EXISTS\s+`?(\w+)`?/i', $statement, $m)) {
            echo "✔ Table ready: {$m[1]}\n";
        }
    }
    echo "\nMigration finished. $ok statement(s) executed.\n";
} catch (PDOException $e) {
    echo "\n✘ Migration failed: " . $e->getMessage() . "\n";
    echo "Failed statement:\n" . substr($statement, 0, 200) . "...\n";
    exit(1);
}
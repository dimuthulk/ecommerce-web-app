<?php
/**
 * migration.php — runs database/migrations.sql on the correct database.
 *
 * Local  : php migration.php            (CLI)   or  http://localhost/.../migration.php
 * Remote : https://your-site.com/migration.php?key=YOUR_SECRET_KEY
 *
 * Remote secret: add this line to config/database.remote.php (the file that is NOT in git):
 *     define('MIGRATION_SECRET', 'long-random-string');
 */

const CONFIG_LOCAL  = __DIR__ . '/config/database.local.php';  // defines $pdo (ecommerce_web_app)
const CONFIG_REMOTE = __DIR__ . '/config/database.remote.php'; // defines $pdo (if0_43161606_ecommerce_web_app)
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
    // CREATE TABLE IF NOT EXISTS does not change a table that already exists,
    // so new columns are added here when they are missing (works on MySQL and MariaDB).
    $newColumns = [
        ['users', 'reset_token_hash', 'VARCHAR(64) NULL DEFAULT NULL'],
        ['users', 'reset_expires_at', 'DATETIME NULL DEFAULT NULL'],
        ['users', 'failed_logins',    'TINYINT UNSIGNED NOT NULL DEFAULT 0'],
        ['users', 'locked_until',     'DATETIME NULL DEFAULT NULL'],
    ];
    $exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    foreach ($newColumns as [$table, $column, $definition]) {
        $exists->execute([$table, $column]);
        if ((int) $exists->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            echo "✔ Column added: $table.$column\n";
        }
    }
    $hasIndex = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_reset_token'")->fetchColumn();
    if ((int) $hasIndex === 0) {
        $pdo->exec('CREATE INDEX idx_users_reset_token ON users (reset_token_hash)');
        echo "✔ Index added: users.idx_users_reset_token\n";
    }

    // Sample products (from data/products.php) are only added when you ask for them:
    //   php migration.php seed          or          /migration?key=...&seed=1
    // Products that already exist (same id/sku) are left alone, so edits are never overwritten.
    $wantsSeed = $isCli ? in_array('seed', $argv ?? [], true) : (($_GET['seed'] ?? '') === '1');
    if ($wantsSeed) {
        $samples = require __DIR__ . '/data/products.php';
        $insert  = $pdo->prepare(
            'INSERT IGNORE INTO products
                (id, sku, name, slug, category, gender, price, sale_price, colors, stock, images, is_featured, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)'
        );
        $added = 0;
        foreach ($samples as $s) {
            $insert->execute([
                $s['id'], $s['sku'], $s['name'], $s['slug'], $s['category'], $s['gender'],
                $s['price'], $s['sale_price'],
                json_encode(array_keys($s['stock'])), json_encode($s['stock']), json_encode($s['images']),
            ]);
            $added += $insert->rowCount();
        }
        echo "✔ Sample products: $added added, " . (count($samples) - $added) . " already there\n";
    } elseif ((int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0) {
        echo "ℹ The products table is empty. Run again with \"seed\" to add the sample products.\n";
    }

    echo "\nMigration finished. $ok statement(s) executed.\n";
} catch (PDOException $e) {
    echo "\n✘ Migration failed: " . $e->getMessage() . "\n";
    echo "Failed statement:\n" . substr($statement, 0, 200) . "...\n";
    exit(1);
}

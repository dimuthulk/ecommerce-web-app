<?php
$pageTitle = 'Home';
require_once __DIR__ . '/components/header.php';

require_once __DIR__ . '/config/database.php';
$dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
$env    = $isLocal ? 'LOCAL' : 'REMOTE';
?>

<section class="section container">
    <p class="eyebrow">The everyday collection</p>
    <h1>Home page coming next.</h1>
    <p>Database connected: <?= e($env) ?> / <?= e($dbName) ?></p>
</section>

<?php require_once __DIR__ . '/components/footer.php'; ?>
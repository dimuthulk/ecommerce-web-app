<?php
// Picks local/remote config automatically and creates $pdo
require_once __DIR__ . '/config/database.php';

$dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
$env    = $isLocal ? 'LOCAL' : 'REMOTE';
?>
<!DOCTYPE html>
<html lang="si">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-commerce Web App</title>
    <!-- Theme CSS සම්බන්ධ කිරීම -->
    <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body>
    <div style="text-align: center; margin-top: 50px;">
        <h1>E-commerce Web App</h1>
        <p style="color: green; font-weight: bold; margin-top: 15px;">
            Database Successfully Connected!<br>
            Environment: <?= htmlspecialchars($env) ?> |
            Database: <?= htmlspecialchars($dbName) ?>
        </p>
    </div>
</body>
</html>
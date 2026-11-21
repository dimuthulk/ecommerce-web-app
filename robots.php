<?php
/**
 * robots.txt, generated so the Sitemap line always uses the real domain.
 * .htaccess maps /robots.txt to this file. No session, no database.
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$host    = (string) ($_SERVER['HTTP_HOST'] ?? '');
$https   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
$folder  = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
$validHost = $host !== '' && preg_match('/^(?:[a-z0-9](?:[a-z0-9.-]{0,251}[a-z0-9])?)(?::\d{1,5})?$/i', $host);

echo "User-agent: *\n";
echo "Allow: /\n";
foreach (['api/', 'cart', 'checkout', 'account', 'orders', 'login', 'register', 'forgot-password', 'reset-password', 'payhere-pay', 'payhere-return', 'payhere-cancel', 'payhere-notify'] as $path) {
    echo 'Disallow: ' . $folder . '/' . $path . "\n";
}
if ($validHost) {
    echo "\nSitemap: " . ($https ? 'https' : 'http') . '://' . $host . $folder . "/sitemap.xml\n";
}

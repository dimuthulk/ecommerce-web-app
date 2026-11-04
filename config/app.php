<?php
// Bootstrap: fixed settings (paths, session, e-mail switch), then the shared code.
// Include this first on every page. Store details (name, fee, contact) are in the admin Settings page.

date_default_timezone_set('Asia/Colombo');

define('APP_TAGLINE', 'Everyday pieces. Considered style.');

// BASE_URL is '' on the live site (project in htdocs root) and
// '/ecommerce-web-app' (or similar) when running locally inside a sub-folder.
$projectRoot = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
$docRoot     = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
define(
    'BASE_URL',
    ($docRoot !== '' && strpos($projectRoot, $docRoot) === 0)
        ? rtrim(substr($projectRoot, strlen($docRoot)), '/')
        : ''
);

// Sessions: the cookie is HTTP-only, same-site, and "secure" on https
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL !== '' ? BASE_URL . '/' : '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

// True on localhost / 127.0.0.1 (development), false on the live site
$hostName = explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0];
define('IS_LOCAL', PHP_SAPI === 'cli' || in_array($hostName, ['localhost', '127.0.0.1', '::1'], true));

// Site address used in e-mail links, e.g. 'https://your-site.infinityfreeapp.com'. Empty = detect from the request.
define('APP_URL', '');

// E-mail is switched on by creating config/mail.local.php (on localhost) or config/mail.remote.php
// (live site). See config/mail.example.php. Without that file nothing is sent: messages are only
// written to storage/mail.log, and the live site says that reset-by-email is not available.
define('MAIL_ENABLED', is_file(__DIR__ . '/' . (IS_LOCAL ? 'mail.local.php' : 'mail.remote.php')));
define('ORDER_EMAILS_ENABLED', MAIL_ENABLED);      // order confirmation e-mails + the notice on the confirmation page

require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/settings.php';

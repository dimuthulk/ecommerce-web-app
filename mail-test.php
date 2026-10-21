<?php
/**
 * mail-test.php — sends ONE test e-mail and shows the whole SMTP conversation (passwords hidden).
 * Good for learning how SMTP works and for finding mistakes in config/mail.*.php.
 *
 *   1. Change TEST_KEY below. 2. Open  /mail-test?key=YOUR_KEY&to=you@example.com
 *   3. DELETE this file afterwards (on the live site never leave it there).
 */
const TEST_KEY = 'dimuthulk';

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/mail.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

if (TEST_KEY === 'change-me') {
    http_response_code(403);
    die("Set TEST_KEY at the top of mail-test.php first.\n");
}
if (!isset($_GET['key']) || !is_string($_GET['key']) || !hash_equals(TEST_KEY, $_GET['key'])) {
    http_response_code(403);
    die('Forbidden');
}

$to = is_string($_GET['to'] ?? null) ? trim($_GET['to']) : '';
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    die("Add  &to=your@email.com  to the address.\n");
}

$settings = mail_settings();
echo "Mail test\n" . str_repeat('=', 60) . "\n";
if ($settings === null) {
    die("No mail settings found. Create config/" . (IS_LOCAL ? 'mail.local.php' : 'mail.remote.php') . " (copy config/mail.example.php).\n");
}
echo 'Server     : ' . $settings['host'] . ':' . ($settings['port'] ?? 587) . ' (' . (($settings['encryption'] ?? 'tls') ?: 'no encryption') . ")\n";
echo 'Login      : ' . (!empty($settings['username']) ? 'yes (name and password are never shown)' : 'none') . "\n";
echo 'From       : ' . $settings['from_email'] . "\n";
echo 'To         : ' . $to . "\n\n";
echo "SMTP conversation (C = our site, S = mail server)\n" . str_repeat('-', 60) . "\n";

$show = static function (string $line): void {
    $line = rtrim($line);
    // PHPMailer already prints "[credentials hidden]" for the login; this also covers "AUTH PLAIN <secret>" lines
    $line = preg_replace('/^(CLIENT -> SERVER: AUTH (?:PLAIN|XOAUTH2)) \S+/', '$1 <hidden>', $line);
    echo str_replace(['CLIENT -> SERVER: ', 'SERVER -> CLIENT: '], ['C: ', 'S: '], $line) . "\n";
};

$ok = send_mail($to, 'Test e-mail from ' . APP_NAME, "Hello!\n\nIf you can read this, SMTP works.\n\nSent at " . date('c') . "\n", ['debug' => $show]);

echo str_repeat('-', 60) . "\n";
echo $ok ? "RESULT: the mail server accepted the message. Check the inbox (and the spam folder).\n"
         : "RESULT: sending failed. Read the last lines above for the reason.\n";
echo "\nDelete mail-test.php now.\n";

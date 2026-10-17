<?php
/**
 * E-mail sending.
 * While MAIL_ENABLED is false (config/app.php) nothing is sent: the message is only written to
 * storage/mail.log so you can see what would have been sent.
 * When MAIL_ENABLED is true it uses PHP's mail(). To use SMTP (PHPMailer) later, replace the
 * mail() call below — every page already goes through send_mail().
 */
function send_mail(string $to, string $subject, string $body): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents(
        $dir . '/mail.log',
        '==== ' . date('c') . " ====\nTo: $to\nSubject: $subject\n\n$body\n\n",
        FILE_APPEND | LOCK_EX
    );

    if (!MAIL_ENABLED) {
        return false;
    }

    $headers = 'From: ' . MAIL_FROM . "\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

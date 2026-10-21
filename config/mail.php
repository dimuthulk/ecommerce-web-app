<?php
/**
 * E-mail sending with PHPMailer (SMTP).
 *
 * send_mail() is the only place the site sends mail from. Settings live in a file that is NOT in git:
 *   config/mail.local.php   (XAMPP / localhost)      config/mail.remote.php   (live site)
 * Copy config/mail.example.php to create one. No file = no e-mail (messages go to storage/mail.log).
 */

/** The SMTP settings for this environment, or null when e-mail is not set up. */
function mail_settings(): ?array
{
    static $settings = false;

    if ($settings === false) {
        $file     = __DIR__ . '/' . (IS_LOCAL ? 'mail.local.php' : 'mail.remote.php');
        $settings = is_file($file) ? require $file : null;

        if (!is_array($settings) || empty($settings['host']) || empty($settings['from_email'])) {
            $settings = null;
        }
    }
    return $settings;
}

/**
 * Send a plain-text e-mail.
 *
 * @param array $options 'reply_to' => address, 'debug' => callable($line) receives the SMTP conversation
 * @return bool true when the mail server accepted the message
 */
function send_mail(string $to, string $subject, string $body, array $options = []): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $settings = mail_settings();

    if ($settings === null) {
        // Development without a mail server: keep a copy so you can see what would have been sent
        $dir = __DIR__ . '/../storage';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($dir . '/mail.log', '==== ' . date('c') . " ====\nTo: $to\nSubject: $subject\n\n$body\n\n", FILE_APPEND | LOCK_EX);
        return false;
    }

    require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
    require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);          // true = throw exceptions

    try {
        $mail->isSMTP();
        $mail->Host    = $settings['host'];
        $mail->Port    = (int) ($settings['port'] ?? 587);
        $mail->Timeout = 10;

        // '' = no encryption (local test server), 'tls' = STARTTLS (port 587), 'ssl' = SSL from the start (port 465)
        $encryption       = $settings['encryption'] ?? 'tls';
        $mail->SMTPSecure = $encryption === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                          : ($encryption === 'tls' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : '');
        $mail->SMTPAutoTLS = $encryption === 'tls';

        $mail->SMTPAuth = !empty($settings['username']);
        if ($mail->SMTPAuth) {
            $mail->Username = $settings['username'];
            $mail->Password = $settings['password'] ?? '';
        }

        $mail->CharSet = 'UTF-8';
        $mail->isHTML(false);
        $mail->setFrom($settings['from_email'], $settings['from_name'] ?? APP_NAME);
        $mail->addAddress($to);
        if (!empty($options['reply_to']) && filter_var($options['reply_to'], FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($options['reply_to']);
        }
        $mail->Subject = $subject;
        $mail->Body    = $body;

        if (!empty($options['debug']) && is_callable($options['debug'])) {
            $mail->SMTPDebug   = PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;     // show the whole conversation
            $mail->Debugoutput = static fn ($line) => $options['debug']($line);
        }

        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('Mail to ' . $to . ' failed: ' . $e->getMessage());
        if (!empty($options['debug']) && is_callable($options['debug'])) {
            $options['debug']('ERROR: ' . $e->getMessage());
        }
        return false;
    }
}

/** The confirmation e-mail a customer gets after placing an order. $order comes from find_order(). */
function send_order_confirmation(array $order): bool
{
    $lines = '';
    foreach ($order['items'] as $item) {
        $lines .= sprintf(
            "- %s (%s / %s) x %d   %s\n",
            $item['product_name'], $item['color'], $item['size'], (int) $item['quantity'], format_price((float) $item['line_total'])
        );
    }

    $address = implode(', ', array_filter([$order['ship_line1'], $order['ship_line2']]))
             . ', ' . trim($order['ship_city'] . ' ' . $order['ship_postal_code']);

    $body = 'Hello ' . (strtok($order['customer_name'], ' ') ?: $order['customer_name']) . ",\n\n"
          . "Thank you for your order. We have received it and the store will confirm delivery details with you.\n\n"
          . 'Order ' . $order['order_number'] . ' - placed ' . date('j F Y', strtotime($order['created_at'])) . "\n\n"
          . $lines . "\n"
          . 'Subtotal: ' . format_price((float) $order['subtotal']) . "\n"
          . 'Delivery: ' . format_price((float) $order['shipping_fee']) . "\n"
          . 'Total:    ' . format_price((float) $order['total_amount']) . " (cash on delivery)\n\n"
          . "Delivery to:\n" . $order['customer_name'] . "\n" . $address . "\n" . $order['customer_phone'] . "\n\n";

    if (!empty($order['user_id'])) {
        $body .= 'You can follow this order in your account: ' . absolute_url('orders/' . $order['order_number']) . "\n\n";
    }
    $body .= APP_NAME;

    return send_mail(
        $order['customer_email'],
        'Your order ' . $order['order_number'],
        $body,
        STORE_SUPPORT_EMAIL !== '' ? ['reply_to' => STORE_SUPPORT_EMAIL] : []
    );
}

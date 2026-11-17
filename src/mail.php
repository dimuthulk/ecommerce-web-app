<?php
/**
 * E-mail sending with PHPMailer (SMTP).
 *
 * send_mail() is the only place the site sends mail from. Settings live in a file that is NOT in git:
 *   config/mail.local.php   (XAMPP / localhost)      config/mail.remote.php   (live site)
 * Copy config/mail.example.php to create one. No file = no e-mail (messages go to storage/mail.log).
 *
 * Each e-mail has a plain-text body and an HTML design (src/email-templates.php). Pass the HTML
 * as the 'html' option; the plain-text body is sent alongside it for clients that do not show HTML.
 */

require_once __DIR__ . '/email-templates.php';

/** The SMTP settings for this environment, or null when e-mail is not set up. */
function mail_settings(): ?array
{
    static $settings = false;

    if ($settings === false) {
        $file     = __DIR__ . '/../config/' . (IS_LOCAL ? 'mail.local.php' : 'mail.remote.php');
        $settings = is_file($file) ? require $file : null;

        if (!is_array($settings) || empty($settings['host']) || empty($settings['from_email'])) {
            $settings = null;
        }
    }
    return $settings;
}

/**
 * Send an e-mail.
 *
 * @param array $options 'html' => HTML body (plain-text $body is then the fallback),
 *                       'reply_to' => address, 'debug' => callable($line) receives the SMTP conversation
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

        $isHtml = isset($options['html']) && is_string($options['html']);

        $mail->CharSet = 'UTF-8';
        $mail->isHTML($isHtml);
        $mail->setFrom($settings['from_email'], $settings['from_name'] ?? app_name());
        $mail->addAddress($to);
        if (!empty($options['reply_to']) && filter_var($options['reply_to'], FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($options['reply_to']);
        }
        $mail->Subject = $subject;
        $mail->Body    = $isHtml ? $options['html'] : $body;
        if ($isHtml) {
            $mail->AltBody = $body;
        }

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

/** Reply-to address for customer e-mails, when the store has a support address. */
function mail_customer_options(): array
{
    return support_email() !== '' ? ['reply_to' => support_email()] : [];
}

/** Line list of the order items, for the plain-text copies. */
function mail_order_lines(array $order): string
{
    $lines = '';
    foreach ($order['items'] as $item) {
        $lines .= sprintf("- %s (%s / %s) x %d   %s\n", $item['product_name'], $item['color'], $item['size'],
            (int) $item['quantity'], format_price((float) $item['line_total']));
    }
    return $lines;
}

/** The link e-mail a user gets to choose a new password. $link is the one-time reset URL. */
function send_password_reset_email(array $user, string $link): bool
{
    $minutes = RESET_TOKEN_MINUTES;
    $body = 'Hello ' . $user['first_name'] . ",\n\n"
          . "Use this link to choose a new password. It can be used once and expires in $minutes minutes:\n\n"
          . $link . "\n\n"
          . "If you did not ask for this, you can ignore this message.\n\n" . app_name();

    return send_mail(
        $user['email'],
        'Reset your ' . app_name() . ' password',
        $body,
        ['html' => email_html_password_reset($user, $link)]
    );
}

/** The confirmation e-mail a customer gets after placing an order. $order comes from find_order(). */
function send_order_confirmation(array $order): bool
{
    $first = email_first_name((string) $order['customer_name']);
    $intro = 'Your order ' . $order['order_number'] . ' has been recorded and is awaiting store confirmation. Here is a copy of your order details.';

    $address = implode(', ', array_filter([$order['ship_line1'], $order['ship_line2']]))
             . ', ' . trim($order['ship_city'] . ' ' . $order['ship_postal_code']);

    $body = 'Hello ' . $first . ",\n\n" . $intro . "\n\n"
          . 'Order ' . $order['order_number'] . ' - placed ' . date('j M Y', strtotime((string) $order['created_at'])) . "\n\n"
          . mail_order_lines($order) . "\n"
          . 'Subtotal: ' . format_price((float) $order['subtotal']) . "\n"
          . 'Delivery: ' . format_price((float) $order['shipping_fee']) . "\n"
          . 'Total:    ' . format_price((float) $order['total_amount']) . " (cash on delivery)\n\n"
          . "Delivery to:\n" . $order['customer_name'] . "\n" . $address . "\n" . $order['customer_phone'] . "\n\n";

    if (!empty($order['user_id'])) {
        $body .= 'You can follow this order in your account: ' . absolute_url('orders/' . $order['order_number']) . "\n\n";
    }
    $body .= app_name();

    return send_mail(
        $order['customer_email'],
        'We received order ' . $order['order_number'],
        $body,
        mail_customer_options() + [
            'html' => email_html_customer_order($order, 'Order update', 'Thanks for your order, ' . $first . '.', $intro),
        ]
    );
}

/** "New order" alert for the administrators listed in Settings. */
function send_admin_order_alert(array $order): bool
{
    $recipients = alert_recipients();
    if (!$recipients) {
        return false;
    }

    $body = 'New order ' . $order['order_number'] . "\n\n"
          . 'Customer: ' . $order['customer_name'] . ' <' . $order['customer_email'] . '> ' . $order['customer_phone'] . "\n"
          . 'Deliver to: ' . implode(', ', array_filter([$order['ship_line1'], $order['ship_line2']])) . ', '
          . trim($order['ship_city'] . ' ' . $order['ship_postal_code']) . "\n\n"
          . mail_order_lines($order) . "\n"
          . 'Total: ' . format_price((float) $order['total_amount']) . " (cash on delivery)\n\n"
          . 'Open the order: ' . absolute_url('admin/order.php?number=' . $order['order_number']);

    $subject = 'New order ' . $order['order_number'] . ' · ' . format_price((float) $order['total_amount']);
    $html    = email_html_admin_alert($order);

    $sent = false;
    foreach ($recipients as $to) {
        $sent = send_mail($to, $subject, $body, ['html' => $html]) || $sent;
    }
    return $sent;
}

/** Tell the customer their order status changed (confirmed, shipped, delivered, cancelled). */
function send_order_status_email(array $order, string $status): bool
{
    $copy = email_status_copy($status);
    if ($copy === null) {
        return false;
    }

    $first = email_first_name((string) $order['customer_name']);
    $intro = $copy[1] . (!empty($order['user_id']) ? ' You can review the latest recorded status in your account.' : '');

    $body = 'Hello ' . $first . ",\n\n" . $intro . "\n\n"
          . 'Order ' . $order['order_number'] . ' - status: ' . ucfirst($status) . "\n"
          . 'Total: ' . format_price((float) $order['total_amount']) . "\n\n";
    if (!empty($order['user_id'])) {
        $body .= 'Follow your order: ' . absolute_url('orders/' . $order['order_number']) . "\n\n";
    }
    $body .= app_name();

    return send_mail(
        $order['customer_email'],
        $order['order_number'] . ' is now ' . ucfirst($status),
        $body,
        mail_customer_options() + [
            'html' => email_html_customer_order($order, 'Order update', $copy[0], $intro),
        ]
    );
}

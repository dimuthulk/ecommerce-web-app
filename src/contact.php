<?php
/** Contact messages: save one (with a limit of 5 per hour per visitor) and tell the store by e-mail. */

/** @return string 'ok' or 'limited' */
function contact_save(array $clean, ?int $userId): string
{
    $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|contact|' . app_name());

    $recent = db()->prepare('SELECT COUNT(*) FROM contact_messages WHERE ip_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
    $recent->execute([$ipHash]);
    if ((int) $recent->fetchColumn() >= 5) {
        return 'limited';
    }

    db()->prepare('INSERT INTO contact_messages (user_id, name, email, order_ref, message, ip_hash) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$userId, $clean['name'], $clean['email'], $clean['order_ref'] !== '' ? $clean['order_ref'] : null, $clean['message'], $ipHash]);

    if (support_email() !== '') {
        send_mail(support_email(), 'New message from ' . $clean['name'],
            "From: {$clean['name']} <{$clean['email']}>\nOrder: " . ($clean['order_ref'] ?: '-') . "\n\n{$clean['message']}",
            ['reply_to' => $clean['email']]);
    }
    return 'ok';
}

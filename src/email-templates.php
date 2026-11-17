<?php
/**
 * HTML e-mail designs (transactional e-mails). Every e-mail is sent as HTML with a plain-text copy
 * (see src/mail.php). Dynamic values are escaped with e() where they go into the HTML.
 */

/** Product image for an order line (absolute URL), or null when the product has none. */
function email_item_image(?int $productId): ?string
{
    if (!$productId) {
        return null;
    }
    $stmt = db()->prepare('SELECT images FROM products WHERE id = ?');
    $stmt->execute([$productId]);
    $images = json_decode((string) $stmt->fetchColumn(), true);

    return is_array($images) && isset($images[0]) ? absolute_url((string) $images[0]) : null;
}

/** Headline and sentence for each status e-mail, or null for statuses that do not send one. */
function email_status_copy(string $status): ?array
{
    $copy = [
        'confirmed' => ['Your order is confirmed.', 'The store has confirmed your order.'],
        'shipped'   => ['Your order is on its way.', 'The store has marked your order as shipped.'],
        'delivered' => ['Your order has been delivered.', 'The store has marked your order as delivered. Thank you for shopping with us!'],
        'cancelled' => ['Your order has been cancelled.', 'The store has cancelled your order. If you did not ask for this, please contact the store.'],
    ];

    return $copy[$status] ?? null;
}

/** First name for greetings ("Alex Silva" -> "Alex"). */
function email_first_name(string $name): string
{
    $first = strtok($name, ' ');

    return $first !== false && $first !== '' ? $first : $name;
}

function email_badge(string $label, bool $pending): string
{
    $bg = $pending ? '#fbf3dc' : '#f1f2f4';
    $fg = $pending ? '#7a5200' : '#111827';

    return '<span style="display:inline-block;padding:5px 10px;border-radius:4px;background:' . $bg
        . ';color:' . $fg . ';font-size:13px;font-weight:700;">' . e($label) . '</span>';
}

/** One "label ... value" table row. $value must already be escaped. */
function email_row(string $label, string $value, bool $bold = false): string
{
    $weight = $bold ? 'font-weight:700;' : '';

    return '<tr>'
        . '<td style="padding:8px 0;font-size:15px;color:#4b5563;' . $weight . '">' . e($label) . '</td>'
        . '<td align="right" style="padding:8px 0;font-size:15px;color:#111827;' . $weight . '">' . $value . '</td>'
        . '</tr>';
}

function email_p(string $html): string
{
    return '<p style="margin:0 0 16px;font-size:16px;line-height:1.6;color:#111827;">' . $html . '</p>';
}

function email_summary(array $order): string
{
    $status = (string) ($order['status'] ?? 'pending');

    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
        . email_row('Order reference', e($order['order_number']))
        . email_row('Order date', e(date('j M Y', strtotime((string) $order['created_at']))))
        . '</table>'
        . '<div style="margin:14px 0 4px;">' . email_badge(ucfirst($status), $status === 'pending') . '</div>';
}

function email_items(array $order): string
{
    $rows = '';
    foreach ($order['items'] as $item) {
        $img   = email_item_image(isset($item['product_id']) ? (int) $item['product_id'] : null);
        $thumb = $img !== null
            ? '<td width="84" valign="top" style="padding:12px 14px 12px 0;border-bottom:1px solid #e5e7eb;">'
              . '<img src="' . e($img) . '" width="72" height="72" alt="' . e($item['product_name']) . '" style="display:block;width:72px;height:72px;border:0;border-radius:4px;"></td>'
            : '';

        $rows .= '<tr>' . $thumb
            . '<td valign="top" style="padding:12px 0;border-bottom:1px solid #e5e7eb;">'
            . '<div style="font-size:16px;font-weight:700;color:#111827;">' . e($item['product_name']) . '</div>'
            . '<div style="font-size:14px;color:#6b7280;margin-top:3px;">' . e($item['color'] . ' / ' . $item['size'])
            . ' &middot; Quantity ' . (int) $item['quantity'] . '</div>'
            . '<div style="font-size:15px;color:#111827;margin-top:4px;">' . e(format_price((float) $item['line_total'])) . '</div>'
            . '</td></tr>';
    }

    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;">' . $rows . '</table>';
}

function email_totals(array $order): string
{
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;">'
        . email_row('Subtotal', e(format_price((float) $order['subtotal'])))
        . email_row('Delivery', e(format_price((float) $order['shipping_fee'])))
        . email_row('Total · cash on delivery', e(format_price((float) $order['total_amount'])), true)
        . '</table>';
}

function email_delivery(array $order, bool $withContact): string
{
    $address = implode(', ', array_filter([$order['ship_line1'], $order['ship_line2']]))
        . ', ' . trim($order['ship_city'] . ' ' . $order['ship_postal_code']);

    $html = '<h3 style="margin:28px 0 10px;font-size:17px;color:#111827;">Delivery details</h3>'
        . '<div style="font-size:15px;line-height:1.6;color:#111827;">' . e($order['customer_name']) . ' &middot; ' . e($address) . '</div>';

    if ($withContact) {
        $html .= '<div style="font-size:15px;line-height:1.6;color:#111827;">'
            . e($order['customer_email']) . ' &middot; ' . e($order['customer_phone']) . '</div>';
    }

    return $html . '<p style="margin:8px 0 0;font-size:13px;line-height:1.5;color:#6b7280;">'
        . 'Delivery timing is confirmed by the store; no delivery promise is made.</p>';
}

function email_button(string $label, string $url): string
{
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:28px 0 12px;"><tr><td>'
        . '<a href="' . e($url) . '" style="display:block;background:#c9302c;color:#ffffff;text-decoration:none;'
        . 'font-weight:700;font-size:16px;text-align:center;padding:14px 20px;border-radius:6px;">' . e($label) . '</a>'
        . '</td></tr></table>'
        . '<p style="margin:0;font-size:13px;line-height:1.5;color:#6b7280;word-break:break-all;">'
        . 'Button not working? Open: <a href="' . e($url) . '" style="color:#6b7280;">' . e($url) . '</a></p>';
}

/**
 * The shell every e-mail shares: brand, eyebrow, headline, content, help line and footer.
 * $content is HTML that is already escaped. The layout collapses to one column on phones.
 */
function email_layout(string $title, string $preheader, string $eyebrow, string $headline, string $content): string
{
    $brand   = e(app_name());
    $support = support_email();

    $help = $support !== ''
        ? '<p style="margin:0;font-size:15px;line-height:1.6;color:#111827;">Need help? Contact <a href="mailto:' . e($support)
          . '" style="color:#111827;">' . e($support) . '</a>.</p>'
        : '';

    return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . '</title>'
        . '<style>@media only screen and (max-width:600px){'
        . '.card{width:100% !important;}.pad{padding:24px 20px !important;}.h1{font-size:26px !important;line-height:1.2 !important;}'
        . '}</style></head>'
        . '<body style="margin:0;padding:0;background:#f5f6f8;font-family:Arial,Helvetica,sans-serif;">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . e($preheader) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f6f8;"><tr>'
        . '<td align="center" style="padding:24px 12px;">'
        . '<table role="presentation" class="card" width="600" cellpadding="0" cellspacing="0" '
        . 'style="width:600px;max-width:600px;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;">'
        . '<tr><td class="pad" style="padding:32px 36px;">'
        . '<div style="font-size:20px;font-weight:700;color:#111827;padding-bottom:18px;border-bottom:1px solid #e5e7eb;">' . $brand . '</div>'
        . '<div style="font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#c9302c;margin-top:26px;">' . e($eyebrow) . '</div>'
        . '<h1 class="h1" style="margin:8px 0 20px;font-size:32px;line-height:1.15;color:#111827;">' . e($headline) . '</h1>'
        . $content
        . '<hr style="border:0;border-top:1px solid #e5e7eb;margin:24px 0;">'
        . $help
        . '<p style="margin:12px 0 0;font-size:13px;line-height:1.5;color:#6b7280;">' . $brand
        . ($support !== '' ? ' &middot; ' . e($support) : '')
        . '<br>This is a transactional message, not a newsletter.</p>'
        . '</td></tr></table></td></tr></table></body></html>';
}

/** Password reset. $link is the one-time reset URL. */
function email_html_password_reset(array $user, string $link): string
{
    $content = email_p(e('Hello ' . $user['first_name'] . ','))
        . email_p(e('We received a request to reset the password for your account. Use the secure link below to choose a new password.'))
        . email_button('Reset password', $link)
        . '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#4b5563;">'
        . e('This secure link can be used once and expires in ' . RESET_TOKEN_MINUTES . ' minutes. If it has expired, request a new one.')
        . '</p>'
        . email_p(e('If you didn’t request this change, ignore this message. Your password will stay unchanged.'));

    return email_layout('Reset your ' . app_name() . ' password', 'Choose a new password', 'Account security', 'Reset your password.', $content);
}

/** Customer e-mails about an order (confirmation and status updates share one design). */
function email_html_customer_order(array $order, string $eyebrow, string $headline, string $intro): string
{
    $content = email_p(e('Hello ' . email_first_name((string) $order['customer_name']) . ','))
        . email_p(e($intro))
        . email_summary($order)
        . email_items($order)
        . email_totals($order)
        . email_delivery($order, false);

    if (!empty($order['user_id'])) {
        $content .= email_button('View order ' . $order['order_number'], absolute_url('orders/' . $order['order_number']));
    }

    return email_layout($headline, $intro, $eyebrow, $headline, $content);
}

/** Store alert to the administrators about a new order. */
function email_html_admin_alert(array $order): string
{
    $content = email_p(e('Hello Administrator,'))
        . email_p(e('A new cash-on-delivery order has been recorded. Review the items and customer details, then confirm the order in the secure admin workspace.'))
        . email_summary($order)
        . email_items($order)
        . email_totals($order)
        . email_delivery($order, true)
        . email_button('Review order in admin', absolute_url('admin/order.php?number=' . $order['order_number']));

    return email_layout(
        'New order ' . $order['order_number'] . ' · ' . format_price((float) $order['total_amount']),
        'A new order is waiting',
        'Store notification',
        'A new order is waiting.',
        $content
    );
}

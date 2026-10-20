<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/auth.php';

$old    = ['name' => '', 'email' => '', 'order_ref' => '', 'message' => ''];
$errors = [];
$length = static fn (string $s): int => function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);

// Signed-in customers: name and email are filled in
$account = is_logged_in() ? auth_user() : null;
if ($account !== null) {
    $old['name']  = user_display_name($account);
    $old['email'] = $account['email'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $field => $_) {
        $old[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
    }

    if (!csrf_valid()) {
        $errors['form'] = 'Your session expired. Please try again.';
    }
    if ($old['name'] === '' || $length($old['name']) > 160) {
        $errors['name'] = 'Please enter your name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || $length($old['email']) > 190) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if ($old['order_ref'] !== '' && !preg_match('/^[A-Za-z0-9-]{1,30}$/', $old['order_ref'])) {
        $errors['order_ref'] = 'Order references look like CB-1001.';
    }
    if ($length($old['message']) < 5 || $length($old['message']) > 2000) {
        $errors['message'] = 'Please write a message (up to 2000 characters).';
    }

    // Hidden "website" box: people never see it, spam bots fill it in. Pretend it worked.
    if (!$errors && !empty($_POST['website'])) {
        toast('Message sent', 'success');
        redirect(url('contact.php'));
    }

    if (!$errors) {
        try {
            $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|contact|' . APP_NAME);
            $recent = db()->prepare('SELECT COUNT(*) FROM contact_messages WHERE ip_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
            $recent->execute([$ipHash]);

            if ((int) $recent->fetchColumn() >= 5) {
                $errors['form'] = 'You have sent a few messages recently. Please try again a little later.';
            } else {
                db()->prepare(
                    'INSERT INTO contact_messages (user_id, name, email, order_ref, message, ip_hash) VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([
                    $account['id'] ?? null, $old['name'], $old['email'],
                    $old['order_ref'] !== '' ? $old['order_ref'] : null, $old['message'], $ipHash,
                ]);

                if (STORE_SUPPORT_EMAIL !== '') {          // best effort; always noted in storage/mail.log
                    send_mail(STORE_SUPPORT_EMAIL, 'New message from ' . $old['name'],
                        "From: {$old['name']} <{$old['email']}>\nOrder: " . ($old['order_ref'] ?: '-') . "\n\n{$old['message']}");
                }

                toast('Message sent', 'success', ['text' => 'The store will get back to you.']);
                redirect(url('contact.php'));
            }
        } catch (Throwable $e) {
            error_log('Contact form failed: ' . $e->getMessage());
            $errors['form'] = 'We could not send your message. Please try again in a moment.';
        }
    }
}

$aria = static fn (string $key): string => isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="err-' . $key . '"' : '';
$err  = static function (string $key) use ($errors): string {
    return isset($errors[$key]) ? '<p class="field-error" id="err-' . $key . '">' . e($errors[$key]) . '</p>' : '';
};

$pageTitle       = 'Contact';
$pageDescription = 'Questions about a piece or an order? Get in touch with the store.';
require_once __DIR__ . '/components/header.php';
?>

<div class="container page-top">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li><a href="<?= url('/') ?>">Home</a></li>
            <li aria-current="page">Contact</li>
        </ol>
    </nav>

    <header class="listing-head">
        <h1>Let’s talk.</h1>
        <p>Questions about a piece or an order? Get in touch with the store.</p>
    </header>

    <?php if (isset($errors['form'])): ?>
        <p class="alert alert--error" role="alert"><?= e($errors['form']) ?></p>
    <?php endif; ?>

    <div class="contact-layout">

        <section class="card form-card" aria-labelledby="message-title">
            <h2 id="message-title">Send a message</h2>
            <form class="form-grid" method="post" action="<?= url('contact.php') ?>" novalidate>
                <?= csrf_field() ?>

                <div class="field">
                    <label for="c-name">Your name <span aria-hidden="true">*</span></label>
                    <input class="input" id="c-name" name="name" type="text" value="<?= e($old['name']) ?>" placeholder="Alex Silva" autocomplete="name" maxlength="160" required<?= $aria('name') ?>>
                    <?= $err('name') ?>
                </div>
                <div class="field">
                    <label for="c-email">Email address <span aria-hidden="true">*</span></label>
                    <input class="input" id="c-email" name="email" type="email" value="<?= e($old['email']) ?>" placeholder="alex@example.com" autocomplete="email" maxlength="190" required<?= $aria('email') ?>>
                    <?= $err('email') ?>
                </div>
                <div class="field">
                    <label for="c-order">Order reference (optional)</label>
                    <input class="input" id="c-order" name="order_ref" type="text" value="<?= e($old['order_ref']) ?>" placeholder="CB-1001" maxlength="30"<?= $aria('order_ref') ?>>
                    <?= $err('order_ref') ?>
                </div>
                <div class="field">
                    <label for="c-message">Message <span aria-hidden="true">*</span></label>
                    <textarea class="textarea" id="c-message" name="message" rows="5" maxlength="2000" placeholder="How can we help?" required<?= $aria('message') ?>><?= e($old['message']) ?></textarea>
                    <?= $err('message') ?>
                </div>

                <!-- spam trap: leave empty -->
                <div class="hp" aria-hidden="true">
                    <label for="c-website">Website</label>
                    <input id="c-website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>

                <div><button type="submit" class="btn btn--primary">Send message</button></div>
            </form>
        </section>

        <aside class="contact-aside">
            <section class="card form-card" aria-labelledby="support-title">
                <h2 id="support-title">Customer support</h2>
                <?php if (STORE_SUPPORT_EMAIL !== '' || STORE_SUPPORT_PHONE !== ''): ?>
                    <?php if (STORE_SUPPORT_EMAIL !== ''): ?><p class="detail-line"><a href="mailto:<?= e(STORE_SUPPORT_EMAIL) ?>"><?= e(STORE_SUPPORT_EMAIL) ?></a></p><?php endif; ?>
                    <?php if (STORE_SUPPORT_PHONE !== ''): ?><p class="detail-line"><?= e(STORE_SUPPORT_PHONE) ?></p><?php endif; ?>
                <?php else: ?>
                    <p class="form-card__text">Use the form and the store will get back to you.</p>
                <?php endif; ?>
            </section>

            <section class="card form-card" aria-labelledby="before-title">
                <h2 id="before-title">Before you write</h2>
                <div class="contact-links">
                    <a class="btn btn--ghost" href="<?= url('delivery-policy.php') ?>">Review delivery policy</a>
                    <a class="btn btn--ghost" href="<?= url('returns-policy.php') ?>">Review returns policy</a>
                    <a class="btn btn--outline" href="<?= url('orders.php') ?>">View my orders</a>
                </div>
            </section>
        </aside>
    </div>

    <p class="confirm-note contact-credit">Gdoop Platforms develops the website. The clothing store handles product and order enquiries.</p>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>

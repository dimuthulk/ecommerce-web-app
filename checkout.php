<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/cart.php';
require_once __DIR__ . '/config/orders.php';
require_once __DIR__ . '/config/auth.php';

/* ---------- 1. The bag must have orderable items ---------- */
$lines = cart_lines();
if (!$lines) {
    toast('Your bag is empty', 'info', ['text' => 'Add something first, then check out.']);
    redirect(url('cart.php'));
}
$totals = cart_totals($lines);
if (!$totals['all_ok']) {
    toast('Please review your bag', 'error', ['text' => 'Some items are no longer available in the quantity you chose.']);
    redirect(url('cart.php'));
}

/* ---------- 2. Place the order (POST) ---------- */
$old    = ['name' => '', 'email' => '', 'phone' => '', 'line1' => '', 'line2' => '', 'city' => '', 'postal' => '', 'notes' => ''];
$errors = [];

// Signed-in customers get their saved details filled in
$account = is_logged_in() ? auth_user() : null;
if ($account !== null) {
    $old = [
        'name'   => user_display_name($account),
        'email'  => $account['email'],
        'phone'  => (string) $account['phone'],
        'line1'  => (string) $account['address_line1'],
        'line2'  => (string) $account['address_line2'],
        'city'   => (string) $account['city'],
        'postal' => (string) $account['postal_code'],
        'notes'  => '',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $field => $_) {
        $old[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
    }
    $length = static fn (string $s): int => function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);

    if (!csrf_valid()) {
        $errors['form'] = 'Your session expired. Please try again.';
    }

    if ($old['name'] === '' || $length($old['name']) > 160) {
        $errors['name'] = 'Please enter your full name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || $length($old['email']) > 190) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    $phoneDigits = preg_replace('/\D/', '', $old['phone']);
    if (!preg_match('/^\+?[0-9 ()\-]{7,20}$/', $old['phone']) || strlen($phoneDigits) < 7) {
        $errors['phone'] = 'Please enter a valid phone number.';
    }
    if ($old['line1'] === '' || $length($old['line1']) > 190) {
        $errors['line1'] = 'Please enter your address.';
    }
    if ($length($old['line2']) > 190) {
        $errors['line2'] = 'Address line 2 is too long.';
    }
    if ($old['city'] === '' || $length($old['city']) > 100) {
        $errors['city'] = 'Please enter your city.';
    }
    if ($old['postal'] === '' || $length($old['postal']) > 20) {
        $errors['postal'] = 'Please enter your postal code.';
    }
    if ($length($old['notes']) > 1000) {
        $errors['notes'] = 'Order notes are too long (1000 characters max).';
    }
    if (($_POST['payment_method'] ?? 'cod') !== 'cod') {
        $errors['payment'] = 'Please choose a payment method.';
    }
    if (empty($_POST['terms'])) {
        $errors['terms'] = 'Please accept the store terms and privacy policy.';
    }

    if (!$errors) {
        try {
            $order = create_order($old, $lines, $totals);
            cart_clear();
            $_SESSION['last_order_id'] = $order['id'];
            redirect(url('order-confirmation.php'));
        } catch (Throwable $e) {
            error_log('Order failed: ' . $e->getMessage());
            $errors['form'] = 'We could not place your order. Please try again in a moment.';
        }
    }
}

/* ---------- 3. View helpers ---------- */
$aria = static fn (string $key): string => isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="err-' . $key . '"' : '';
$err  = static function (string $key) use ($errors): string {
    return isset($errors[$key]) ? '<p class="field-error" id="err-' . $key . '">' . e($errors[$key]) . '</p>' : '';
};

$pageTitle       = 'Checkout';
$pageDescription = 'Delivery details and payment.';
require_once __DIR__ . '/components/header.php';
?>

<div class="container page-top">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li><a href="<?= url('/') ?>">Home</a></li>
            <li aria-current="page">Checkout</li>
        </ol>
    </nav>

    <header class="listing-head">
        <h1>One last step.</h1>
        <p><a href="<?= url('cart.php') ?>">Bag</a> &rarr; Delivery &amp; payment &rarr; Confirmation</p>
    </header>

    <?php if (isset($errors['form'])): ?>
        <p class="alert alert--error" role="alert"><?= e($errors['form']) ?></p>
    <?php elseif ($errors): ?>
        <p class="alert alert--error" role="alert">Please check the highlighted fields.</p>
    <?php endif; ?>

    <div class="checkout-layout">

        <form class="checkout-main" method="post" action="<?= url('checkout.php') ?>" novalidate>
            <?= csrf_field() ?>

            <section class="card form-card">
                <h2>Contact details</h2>
                <p class="form-card__intro">
                    <?php if ($account !== null): ?>
                        Signed in as <strong><?= e($account['email']) ?></strong>. We filled in your saved details.
                    <?php else: ?>
                        Already have an account? <a href="<?= url('login.php') ?>?next=<?= rawurlencode(url('checkout.php')) ?>">Log in</a> to use your saved details.
                    <?php endif; ?>
                </p>

                <div class="form-grid">
                    <div class="field">
                        <label for="f-name">Full name <span aria-hidden="true">*</span></label>
                        <input class="input" id="f-name" name="name" type="text" value="<?= e($old['name']) ?>" placeholder="Alex Silva" autocomplete="name" maxlength="160" required<?= $aria('name') ?>>
                        <?= $err('name') ?>
                    </div>
                    <div class="field">
                        <label for="f-email">Email address <span aria-hidden="true">*</span></label>
                        <input class="input" id="f-email" name="email" type="email" value="<?= e($old['email']) ?>" placeholder="alex@example.com" autocomplete="email" maxlength="190" required<?= $aria('email') ?>>
                        <?= $err('email') ?>
                    </div>
                    <div class="field">
                        <label for="f-phone">Phone number <span aria-hidden="true">*</span></label>
                        <input class="input" id="f-phone" name="phone" type="tel" value="<?= e($old['phone']) ?>" placeholder="+94 77 000 0000" autocomplete="tel" maxlength="20" required<?= $aria('phone') ?>>
                        <?= $err('phone') ?>
                    </div>
                </div>
            </section>

            <section class="card form-card">
                <h2>Delivery address</h2>

                <div class="form-grid">
                    <div class="field">
                        <label for="f-line1">Address line 1 <span aria-hidden="true">*</span></label>
                        <input class="input" id="f-line1" name="line1" type="text" value="<?= e($old['line1']) ?>" placeholder="24 Sample Lane" autocomplete="address-line1" maxlength="190" required<?= $aria('line1') ?>>
                        <?= $err('line1') ?>
                    </div>
                    <div class="field">
                        <label for="f-line2">Address line 2 (optional)</label>
                        <input class="input" id="f-line2" name="line2" type="text" value="<?= e($old['line2']) ?>" placeholder="Apartment / landmark" autocomplete="address-line2" maxlength="190"<?= $aria('line2') ?>>
                        <?= $err('line2') ?>
                    </div>
                    <div class="form-grid__pair">
                        <div class="field">
                            <label for="f-city">City <span aria-hidden="true">*</span></label>
                            <input class="input" id="f-city" name="city" type="text" value="<?= e($old['city']) ?>" placeholder="Colombo" autocomplete="address-level2" maxlength="100" required<?= $aria('city') ?>>
                            <?= $err('city') ?>
                        </div>
                        <div class="field">
                            <label for="f-postal">Postal code <span aria-hidden="true">*</span></label>
                            <input class="input" id="f-postal" name="postal" type="text" value="<?= e($old['postal']) ?>" placeholder="00100" autocomplete="postal-code" maxlength="20" required<?= $aria('postal') ?>>
                            <?= $err('postal') ?>
                        </div>
                    </div>
                    <div class="field">
                        <label for="f-notes">Order notes (optional)</label>
                        <textarea class="textarea" id="f-notes" name="notes" rows="4" maxlength="1000" placeholder="No additional instructions"<?= $aria('notes') ?>><?= e($old['notes']) ?></textarea>
                        <?= $err('notes') ?>
                    </div>
                </div>
            </section>

            <section class="card form-card">
                <h2>Payment method</h2>
                <label class="check">
                    <input type="radio" name="payment_method" value="cod" checked>
                    <span>Cash on delivery</span>
                </label>
                <p class="form-card__text">Pay <?= e(format_price($totals['total'])) ?> when your order is delivered. No card information is collected.</p>
                <?= $err('payment') ?>
            </section>

            <div class="checkout-confirm">
                <label class="check">
                    <input type="checkbox" name="terms" value="1"<?= !empty($_POST['terms']) ? ' checked' : '' ?><?= $aria('terms') ?>>
                    <span>I have read the <a href="<?= url('terms.php') ?>">store terms</a> and <a href="<?= url('privacy-policy.php') ?>">privacy policy</a>. <span aria-hidden="true">*</span></span>
                </label>
                <?= $err('terms') ?>

                <button type="submit" class="btn btn--primary btn--block">Place order &middot; <?= e(format_price($totals['total'])) ?></button>
                <a class="btn btn--ghost" href="<?= url('cart.php') ?>">Back to bag</a>
            </div>
        </form>

        <aside class="checkout-aside">
            <section class="card" aria-labelledby="items-title">
                <h2 class="summary__title" id="items-title"><?= $totals['items'] === 1 ? 'Your item' : 'Your items' ?></h2>
                <ul class="mini-lines">
                    <?php foreach ($lines as $line): ?>
                        <?php $product = $line['product']; $image = $product['images'][0] ?? null; ?>
                    <li class="mini-line">
                        <span class="mini-line__image">
                            <?= $image ? '<img src="' . e(url($image)) . '" alt="">' : image_placeholder($product['sku'], true, 'thumb') ?>
                        </span>
                        <span class="mini-line__info">
                            <span class="mini-line__name"><?= e($product['name']) ?></span>
                            <span class="mini-line__meta"><?= e($line['color'] . ' / ' . $line['size']) ?> &middot; Quantity <?= $line['qty'] ?></span>
                            <span class="mini-line__price"><?= e(format_price($line['line_total'])) ?></span>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <?php include __DIR__ . '/components/order-summary.php'; ?>
        </aside>
    </div>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>

<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/auth.php';
header('Cache-Control: no-store');

$user   = require_login();
$errors = [];
$length = static fn (string $s): int => function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
$text   = static fn (string $key): string => is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';

// Values shown in the forms (replaced by what was typed when a form has errors)
$profile = ['name' => user_display_name($user), 'email' => $user['email'], 'phone' => (string) $user['phone']];
$address = ['line1' => (string) $user['address_line1'], 'city' => (string) $user['city'], 'postal' => (string) $user['postal_code']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $text('form');

    if (!csrf_valid()) {
        $errors['form'] = 'Your session expired. Please try again.';

    } elseif ($form === 'profile') {
        $profile = ['name' => $text('name'), 'email' => $text('email'), 'phone' => $text('phone')];

        if ($profile['name'] === '' || $length($profile['name']) > 160) {
            $errors['name'] = 'Please enter your full name.';
        }
        if (!filter_var($profile['email'], FILTER_VALIDATE_EMAIL) || $length($profile['email']) > 190) {
            $errors['email'] = 'Please enter a valid email address.';
        } else {
            $other = user_find_by_email($profile['email']);
            if ($other !== null && (int) $other['id'] !== (int) $user['id']) {
                $errors['email'] = 'Another account already uses this email.';
            }
        }
        if ($profile['phone'] !== '' && (!preg_match('/^\+?[0-9 ()\-]{7,20}$/', $profile['phone']) || strlen(preg_replace('/\D/', '', $profile['phone'])) < 7)) {
            $errors['phone'] = 'Please enter a valid phone number.';
        }

        if (!$errors) {
            [$first, $last] = split_name($profile['name']);
            try {
                db()->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?')
                    ->execute([$first, $last, strtolower($profile['email']), $profile['phone'] !== '' ? $profile['phone'] : null, $user['id']]);
                toast('Profile saved', 'success');
                redirect(url('account.php'));
            } catch (PDOException $e) {
                $errors['email'] = $e->getCode() === '23000' ? 'Another account already uses this email.' : 'We could not save your profile. Please try again.';
            }
        }

    } elseif ($form === 'address') {
        $address = ['line1' => $text('line1'), 'city' => $text('city'), 'postal' => $text('postal')];

        if ($length($address['line1']) > 190) $errors['line1'] = 'Address line 1 is too long.';
        if ($length($address['city']) > 100)  $errors['city']  = 'City is too long.';
        if ($length($address['postal']) > 20) $errors['postal'] = 'Postal code is too long.';

        if (!$errors) {
            $or = static fn (string $v) => $v !== '' ? $v : null;
            db()->prepare('UPDATE users SET address_line1 = ?, city = ?, postal_code = ? WHERE id = ?')
                ->execute([$or($address['line1']), $or($address['city']), $or($address['postal']), $user['id']]);
            toast('Delivery address saved', 'success');
            redirect(url('account.php'));
        }
    }
}

$aria = static fn (string $key): string => isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="err-' . $key . '"' : '';
$err  = static function (string $key) use ($errors): string {
    return isset($errors[$key]) ? '<p class="field-error" id="err-' . $key . '">' . e($errors[$key]) . '</p>' : '';
};

$activeTab = 'profile';
$pageTitle = 'Your account';
require_once __DIR__ . '/components/header.php';
?>

<div class="container page-top">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li><a href="<?= url('/') ?>">Home</a></li>
            <li aria-current="page">Account profile</li>
        </ol>
    </nav>

    <header class="listing-head">
        <h1>Hello, <?= e($user['first_name']) ?>.</h1>
        <p>Your details, all in one place.</p>
    </header>

    <?php include __DIR__ . '/components/account-tabs.php'; ?>

    <?php if (isset($errors['form'])): ?>
        <p class="alert alert--error" role="alert"><?= e($errors['form']) ?></p>
    <?php endif; ?>

    <div class="account-grid">

        <section class="card form-card" aria-labelledby="personal-title">
            <h2 id="personal-title">Personal details</h2>
            <form class="form-grid" method="post" action="<?= url('account.php') ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="profile">

                <div class="field">
                    <label for="p-name">Full name <span aria-hidden="true">*</span></label>
                    <input class="input" id="p-name" name="name" type="text" value="<?= e($profile['name']) ?>" autocomplete="name" maxlength="160" required<?= $aria('name') ?>>
                    <?= $err('name') ?>
                </div>
                <div class="field">
                    <label for="p-email">Email address <span aria-hidden="true">*</span></label>
                    <input class="input" id="p-email" name="email" type="email" value="<?= e($profile['email']) ?>" autocomplete="email" maxlength="190" required<?= $aria('email') ?>>
                    <?= $err('email') ?>
                </div>
                <div class="field">
                    <label for="p-phone">Phone number</label>
                    <input class="input" id="p-phone" name="phone" type="tel" value="<?= e($profile['phone']) ?>" placeholder="+94 77 000 0000" autocomplete="tel" maxlength="20"<?= $aria('phone') ?>>
                    <?= $err('phone') ?>
                </div>
                <div><button type="submit" class="btn btn--primary">Save profile</button></div>
            </form>
        </section>

        <section class="card form-card" aria-labelledby="address-title">
            <h2 id="address-title">Default delivery address</h2>
            <form class="form-grid" method="post" action="<?= url('account.php') ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="address">

                <div class="field">
                    <label for="a-line1">Address line 1</label>
                    <input class="input" id="a-line1" name="line1" type="text" value="<?= e($address['line1']) ?>" placeholder="24 Sample Lane" autocomplete="address-line1" maxlength="190"<?= $aria('line1') ?>>
                    <?= $err('line1') ?>
                </div>
                <div class="field">
                    <label for="a-city">City</label>
                    <input class="input" id="a-city" name="city" type="text" value="<?= e($address['city']) ?>" placeholder="Colombo" autocomplete="address-level2" maxlength="100"<?= $aria('city') ?>>
                    <?= $err('city') ?>
                </div>
                <div class="field">
                    <label for="a-postal">Postal code</label>
                    <input class="input" id="a-postal" name="postal" type="text" value="<?= e($address['postal']) ?>" placeholder="00100" autocomplete="postal-code" maxlength="20"<?= $aria('postal') ?>>
                    <?= $err('postal') ?>
                </div>
                <p class="form-note form-note--flush">Confirm delivery details again at checkout.</p>
                <div><button type="submit" class="btn btn--outline">Save address</button></div>
            </form>
        </section>

        <section class="card form-card account-grid__wide" aria-labelledby="security-title">
            <h2 id="security-title">Account security</h2>
            <p class="form-card__text">Change your password with a secure reset link.</p>
            <form method="post" action="<?= url('forgot-password.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="email" value="<?= e($user['email']) ?>">
                <button type="submit" class="btn btn--outline">Request password reset</button>
            </form>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>

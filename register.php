<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/auth.php';
header('Cache-Control: no-store');

if (is_logged_in() && auth_user() !== null) {
    redirect(url('account.php'));
}

$next   = safe_next($_POST['next'] ?? $_GET['next'] ?? '', '');
$old    = ['name' => '', 'email' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $field => $_) {
        $old[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
    }
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm  = is_string($_POST['confirm'] ?? null) ? $_POST['confirm'] : '';
    $length   = static fn (string $s): int => function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);

    if (!csrf_valid()) {
        $errors['form'] = 'Your session expired. Please try again.';
    }
    if ($old['name'] === '' || $length($old['name']) > 160) {
        $errors['name'] = 'Please enter your full name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || $length($old['email']) > 190) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif (user_find_by_email($old['email']) !== null) {
        $errors['email'] = 'An account with this email already exists. Try signing in.';
    }
    if ($problem = password_problem($password)) {
        $errors['password'] = $problem;
    } elseif ($password !== $confirm) {
        $errors['confirm'] = 'The two passwords do not match.';
    }
    if (empty($_POST['terms'])) {
        $errors['terms'] = 'Please accept the privacy policy and store terms.';
    }

    if (!$errors) {
        try {
            $userId = user_create($old['name'], $old['email'], $password);
            login_user($userId);
            toast('Welcome, ' . split_name($old['name'])[0], 'success', ['text' => 'Your account is ready.']);
            redirect(safe_next($next, url('account.php')));
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {            // the email was taken a moment ago
                $errors['email'] = 'An account with this email already exists. Try signing in.';
            } else {
                error_log('Register failed: ' . $e->getMessage());
                $errors['form'] = 'We could not create your account. Please try again in a moment.';
            }
        }
    }
}

$aria = static fn (string $key): string => isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="err-' . $key . '"' : '';
$err  = static function (string $key) use ($errors): string {
    return isset($errors[$key]) ? '<p class="field-error" id="err-' . $key . '">' . e($errors[$key]) . '</p>' : '';
};

$pageTitle = 'Create account';
require_once __DIR__ . '/components/header.php';
?>

<div class="container page-top">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li><a href="<?= url('/') ?>">Home</a></li>
            <li aria-current="page">Register</li>
        </ol>
    </nav>

    <div class="auth">
        <?php include __DIR__ . '/components/auth-panel.php'; ?>

        <div class="auth-main">
            <h1>Make it yours.</h1>
            <p class="auth-lead">Create an account to manage your profile and orders.</p>

            <?php if (isset($errors['form'])): ?>
                <p class="alert alert--error" role="alert"><?= e($errors['form']) ?></p>
            <?php endif; ?>

            <form class="auth-form" method="post" action="<?= url('register.php') ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">

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
                    <label for="f-password">Password <span aria-hidden="true">*</span></label>
                    <input class="input" id="f-password" name="password" type="password" autocomplete="new-password" maxlength="200" required<?= $aria('password') ?>>
                    <?= $err('password') ?>
                    <p class="field-hint">At least 12 characters recommended.</p>
                </div>
                <div class="field">
                    <label for="f-confirm">Confirm password <span aria-hidden="true">*</span></label>
                    <input class="input" id="f-confirm" name="confirm" type="password" autocomplete="new-password" maxlength="200" required<?= $aria('confirm') ?>>
                    <?= $err('confirm') ?>
                </div>

                <div>
                    <label class="check">
                        <input type="checkbox" name="terms" value="1"<?= !empty($_POST['terms']) ? ' checked' : '' ?><?= $aria('terms') ?>>
                        <span>I have read the <a href="<?= url('privacy-policy.php') ?>">privacy policy</a> and <a href="<?= url('terms.php') ?>">store terms</a>. <span aria-hidden="true">*</span></span>
                    </label>
                    <?= $err('terms') ?>
                </div>

                <div class="auth-actions">
                    <button type="submit" class="btn btn--primary btn--block">Create account</button>
                    <a class="btn btn--ghost btn--block" href="<?= url('login.php') ?><?= $next !== '' ? '?next=' . rawurlencode($next) : '' ?>">Already registered? Sign in</a>
                </div>

                <p class="auth-note">Your account information is handled under our <a href="<?= url('privacy-policy.php') ?>">privacy policy</a>.</p>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>

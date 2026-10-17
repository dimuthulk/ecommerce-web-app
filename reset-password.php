<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/auth.php';
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');          // the secret token is in the address

$token = is_string($_POST['token'] ?? $_GET['token'] ?? null) ? ($_POST['token'] ?? $_GET['token']) : '';
$user  = user_by_reset_token($token);
$error = '';
$errors = [];

if ($user !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm  = is_string($_POST['confirm'] ?? null) ? $_POST['confirm'] : '';

    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($problem = password_problem($password)) {
        $errors['password'] = $problem;
    } elseif ($password !== $confirm) {
        $errors['confirm'] = 'The two passwords do not match.';
    } else {
        password_set((int) $user['id'], $password);
        toast('Password updated', 'success', ['text' => 'Sign in with your new password.']);
        redirect(url('login.php'));
    }
}

$aria = static fn (string $key): string => isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="err-' . $key . '"' : '';
$err  = static function (string $key) use ($errors): string {
    return isset($errors[$key]) ? '<p class="field-error" id="err-' . $key . '">' . e($errors[$key]) . '</p>' : '';
};

$noReferrer = true;
$pageTitle  = 'Reset password';
require_once __DIR__ . '/components/header.php';
?>

<div class="container page-top">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li><a href="<?= url('/') ?>">Home</a></li>
            <li aria-current="page">Reset password</li>
        </ol>
    </nav>

    <div class="auth">
        <?php include __DIR__ . '/components/auth-panel.php'; ?>

        <div class="auth-main">

        <?php if ($user === null): ?>

            <h1>This link has expired.</h1>
            <p class="auth-lead">Reset links can only be used once and expire after <?= RESET_TOKEN_MINUTES ?> minutes. Request a new one and we will send it right away.</p>

            <div class="auth-actions">
                <a class="btn btn--primary btn--block" href="<?= url('forgot-password.php') ?>">Request a new link</a>
                <a class="btn btn--ghost btn--block" href="<?= url('login.php') ?>">Already registered? Sign in</a>
            </div>

        <?php else: ?>

            <h1>A fresh start.</h1>
            <p class="auth-lead">Choose a strong new password. This link can only be used once.</p>

            <?php if ($error): ?>
                <p class="alert alert--error" role="alert"><?= e($error) ?></p>
            <?php endif; ?>

            <form class="auth-form" method="post" action="<?= url('reset-password.php') ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="field">
                    <label for="f-password">New password <span aria-hidden="true">*</span></label>
                    <input class="input" id="f-password" name="password" type="password" autocomplete="new-password" maxlength="200" required<?= $aria('password') ?>>
                    <?= $err('password') ?>
                </div>
                <div class="field">
                    <label for="f-confirm">Confirm new password <span aria-hidden="true">*</span></label>
                    <input class="input" id="f-confirm" name="confirm" type="password" autocomplete="new-password" maxlength="200" required<?= $aria('confirm') ?>>
                    <?= $err('confirm') ?>
                    <p class="field-hint">Both passwords must match.</p>
                </div>

                <div class="auth-actions">
                    <button type="submit" class="btn btn--primary btn--block">Save new password</button>
                    <a class="btn btn--ghost btn--block" href="<?= url('login.php') ?>">Already registered? Sign in</a>
                </div>

                <p class="auth-note">Your account information is handled under our <a href="<?= url('privacy-policy.php') ?>">privacy policy</a>.</p>
            </form>

        <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>

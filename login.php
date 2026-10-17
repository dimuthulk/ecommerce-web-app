<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/auth.php';
header('Cache-Control: no-store');

if (is_logged_in() && auth_user() !== null) {
    redirect(url('account.php'));
}

$next  = safe_next($_POST['next'] ?? $_GET['next'] ?? '', '');
$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $result = auth_attempt($email, $password);

        if ($result['user'] !== null) {
            login_user((int) $result['user']['id']);
            toast('Welcome back, ' . $result['user']['first_name'], 'success');
            redirect(safe_next($next, url('account.php')));
        }
        $error = $result['error'] === 'locked'
            ? 'Too many attempts. Please try again in ' . LOGIN_LOCK_MINUTES . ' minutes, or reset your password.'
            : 'The email or password is incorrect.';
    }
}

$pageTitle = 'Sign in';
require_once __DIR__ . '/components/header.php';
?>

<div class="container page-top">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li><a href="<?= url('/') ?>">Home</a></li>
            <li aria-current="page">Login</li>
        </ol>
    </nav>

    <div class="auth">
        <?php include __DIR__ . '/components/auth-panel.php'; ?>

        <div class="auth-main">
            <h1>Welcome back.</h1>
            <p class="auth-lead">Sign in to your account to pick up where you left off.</p>

            <?php if ($error): ?>
                <p class="alert alert--error" role="alert"><?= e($error) ?></p>
            <?php endif; ?>

            <form class="auth-form" method="post" action="<?= url('login.php') ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">

                <div class="field">
                    <label for="f-email">Email address <span aria-hidden="true">*</span></label>
                    <input class="input" id="f-email" name="email" type="email" value="<?= e($email) ?>" autocomplete="username" maxlength="190" required>
                </div>
                <div class="field">
                    <label for="f-password">Password <span aria-hidden="true">*</span></label>
                    <input class="input" id="f-password" name="password" type="password" autocomplete="current-password" maxlength="200" required>
                </div>

                <div class="auth-actions">
                    <a class="btn btn--ghost auth-actions__start" href="<?= url('forgot-password.php') ?>">Forgot password?</a>
                    <button type="submit" class="btn btn--primary btn--block">Sign in</button>
                    <a class="btn btn--ghost btn--block" href="<?= url('register.php') ?><?= $next !== '' ? '?next=' . rawurlencode($next) : '' ?>">New here? Create an account</a>
                </div>

                <p class="auth-note">Your account information is handled under our <a href="<?= url('privacy-policy.php') ?>">privacy policy</a>.</p>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>

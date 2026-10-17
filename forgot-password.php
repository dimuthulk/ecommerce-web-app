<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/auth.php';
header('Cache-Control: no-store');

$state = 'form';            // form | sent | unavailable
$email = '';
$error = '';
$devLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';

    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $error = 'Please enter a valid email address.';
    } else {
        $result  = request_password_reset($email);
        $state   = $result['available'] ? 'sent' : 'unavailable';
        $devLink = $result['link'];
    }
}

$pageTitle = 'Forgot password';
require_once __DIR__ . '/components/header.php';
?>

<div class="container page-top">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li><a href="<?= url('/') ?>">Home</a></li>
            <li aria-current="page">Forgot password</li>
        </ol>
    </nav>

    <div class="auth">
        <?php include __DIR__ . '/components/auth-panel.php'; ?>

        <div class="auth-main">

        <?php if ($state === 'sent'): ?>

            <h1>Check your inbox.</h1>
            <p class="auth-lead">If an account exists for <strong><?= e($email) ?></strong>, we have sent a secure link to reset the password. It can be used once and expires in <?= RESET_TOKEN_MINUTES ?> minutes.</p>
            <p class="auth-note">Nothing arrived? Check your spam folder, or <a href="<?= url('forgot-password.php') ?>">request another link</a> after a minute.</p>

            <?php if ($devLink): ?>
                <p class="alert" role="status"><strong>Development only.</strong> E-mail is not sent from localhost, so here is the link (also saved in <code>storage/mail.log</code>):<br><a href="<?= e($devLink) ?>"><?= e($devLink) ?></a></p>
            <?php endif; ?>

            <div class="auth-actions">
                <a class="btn btn--ghost btn--block" href="<?= url('login.php') ?>">Back to sign in</a>
            </div>

        <?php elseif ($state === 'unavailable'): ?>

            <h1>Reset by email isn't available yet.</h1>
            <p class="auth-lead">Please contact the store and we will help you get back into your account.</p>

            <div class="auth-actions">
                <a class="btn btn--primary btn--block" href="<?= url('contact.php') ?>">Contact the store</a>
                <a class="btn btn--ghost btn--block" href="<?= url('login.php') ?>">Already registered? Sign in</a>
            </div>

        <?php else: ?>

            <h1>Forgot your password?</h1>
            <p class="auth-lead">Enter your account email. We'll send you a secure link to reset your password.</p>

            <?php if ($error): ?>
                <p class="alert alert--error" role="alert"><?= e($error) ?></p>
            <?php endif; ?>

            <form class="auth-form" method="post" action="<?= url('forgot-password.php') ?>" novalidate>
                <?= csrf_field() ?>

                <div class="field">
                    <label for="f-email">Email address <span aria-hidden="true">*</span></label>
                    <input class="input" id="f-email" name="email" type="email" value="<?= e($email) ?>" autocomplete="email" maxlength="190" required>
                </div>

                <div class="auth-actions">
                    <button type="submit" class="btn btn--primary btn--block">Send reset link</button>
                    <a class="btn btn--ghost btn--block" href="<?= url('login.php') ?>">Already registered? Sign in</a>
                </div>

                <p class="auth-note">Your account information is handled under our <a href="<?= url('privacy-policy.php') ?>">privacy policy</a>.</p>
            </form>

        <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>

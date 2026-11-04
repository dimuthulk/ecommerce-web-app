<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../src/auth.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$user = is_logged_in() ? auth_user() : null;
if ($user !== null && $user['role'] === 'admin') {
    redirect(url('admin/'));
}

$adminPage  = 'login';
$adminTitle = 'Admin sign in';
$adminBare  = true;
require __DIR__ . '/../components/admin-header.php';
?>
<div class="auth admin-auth">
    <aside class="auth-panel">
        <p class="eyebrow">Secure store workspace</p>
        <p class="auth-panel__title">Considered clothing.<br>Clear operations.</p>
        <p class="auth-panel__text">Manage products, variant stock and orders. Access is restricted to authenticated administrators.</p>
        <p class="auth-panel__brand"><?= e(app_name()) ?></p>
    </aside>

    <div class="auth-main">
        <h1>Admin sign in</h1>
        <p class="auth-lead">Sign in with your administrator credentials.</p>
        <p class="alert alert--error" id="login-error" role="alert" hidden></p>

        <form class="auth-form" id="admin-login" novalidate>
            <div class="field">
                <label for="f-email">Email address <span aria-hidden="true">*</span></label>
                <input class="input" id="f-email" name="email" type="email" autocomplete="username" maxlength="190" required>
            </div>
            <div class="field">
                <label for="f-password">Password <span aria-hidden="true">*</span></label>
                <input class="input" id="f-password" name="password" type="password" autocomplete="current-password" maxlength="200" required>
            </div>
            <div class="auth-actions">
                <a class="btn btn--ghost auth-actions__start" href="<?= url('forgot-password.php') ?>">Forgot password?</a>
                <button type="submit" class="btn btn--primary btn--block">Sign in</button>
            </div>
            <p class="auth-note">Authenticated admin only. Sessions and credentials are protected in production.</p>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../components/admin-footer.php'; ?>

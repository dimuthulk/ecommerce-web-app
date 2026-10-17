<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/auth.php';

// Signing out is a POST (with the CSRF token) so another site cannot sign people out with a link
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    logout_user();
    toast('Signed out', 'info', ['text' => 'See you soon.']);
}
redirect(url('/'));

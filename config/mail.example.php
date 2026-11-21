<?php
/**
 * Copy this file to  config/mail.local.php  (XAMPP) or  config/mail.remote.php  (live site)
 * and fill it in. Those two files are in .gitignore: they hold passwords, never commit them.
 * Having the file is what switches e-mail on.
 */

// ---- A) Mailpit on your own PC (nothing real is sent; read mails at http://localhost:8025) ----
return [
    'host'       => '127.0.0.1',
    'port'       => 1025,
    'encryption' => '',                 // '' none | 'tls' STARTTLS (587) | 'ssl' SSL (465)
    'username'   => '',
    'password'   => '',
    'from_email' => 'no-reply@example.com',
    'from_name'  => 'Clothing Brand',
];

// ---- B) Gmail (needs 2-Step Verification + an "App password"; Gmail sends from your own address) ----
// return [
//     'host'       => 'smtp.gmail.com',
//     'port'       => 587,
//     'encryption' => 'tls',
//     'username'   => 'you@gmail.com',
//     'password'   => 'abcd efgh ijkl mnop',     // the 16-letter App password
//     'from_email' => 'you@gmail.com',
//     'from_name'  => 'Clothing Brand',
// ];

// ---- C) Brevo (free plan; use the SMTP login and key from Brevo > SMTP & API) ----
// return [
//     'host'       => 'smtp-relay.brevo.com',
//     'port'       => 587,
//     'encryption' => 'tls',
//     'username'   => 'your-brevo-login',
//     'password'   => 'your-brevo-smtp-key',
//     'from_email' => 'a-sender-you-verified-in-brevo@example.com',
//     'from_name'  => 'Clothing Brand',
// ];

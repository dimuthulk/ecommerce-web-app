<?php
date_default_timezone_set('Asia/Colombo');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_NAME', '[Clothing Brand]');
define('APP_TAGLINE', 'Everyday pieces. Considered style.');
define('APP_CURRENCY', 'LKR');

$projectRoot = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
$docRoot     = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
define(
    'BASE_URL',
    ($docRoot !== '' && strpos($projectRoot, $docRoot) === 0)
        ? rtrim(substr($projectRoot, strlen($docRoot)), '/')
        : ''
);

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = __DIR__ . '/../assets/' . $path;
    $v    = is_file($file) ? '?v=' . filemtime($file) : '';
    return BASE_URL . '/assets/' . $path . $v;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function format_price(float $amount): string
{
    return APP_CURRENCY . ' ' . number_format($amount);
}

function cart_count(): int
{
    return (int) array_sum($_SESSION['cart'] ?? []);
}
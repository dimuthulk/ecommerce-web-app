<?php
// Global app config + small helpers. Include this first on every page.

date_default_timezone_set('Asia/Colombo');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_NAME', '[Clothing Brand]');            // replace when the client supplies the brand name
define('APP_TAGLINE', 'Everyday pieces. Considered style.');
define('APP_CURRENCY', 'LKR');

// BASE_URL is '' on the live site (project in htdocs root) and
// '/ecommerce-web-app' (or similar) when running locally inside a sub-folder.
$projectRoot = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
$docRoot     = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
define(
    'BASE_URL',
    ($docRoot !== '' && strpos($projectRoot, $docRoot) === 0)
        ? rtrim(substr($projectRoot, strlen($docRoot)), '/')
        : ''
);

/** Full path to a page:  url('cart.php') */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Path to a file in /assets with a cache-busting version:  asset('css/style.css') */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = __DIR__ . '/../assets/' . $path;
    $v    = is_file($file) ? '?v=' . filemtime($file) : '';
    return BASE_URL . '/assets/' . $path . $v;
}

/** Escape text for safe output in HTML */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** LKR 2,400 */
function format_price(float $amount): string
{
    return APP_CURRENCY . ' ' . number_format($amount);
}

/** Items in the bag. Assumes $_SESSION['cart'] = [ item_key => quantity ] */
function cart_count(): int
{
    return (int) array_sum($_SESSION['cart'] ?? []);
}

/**
 * Grey image placeholder shown until real photos exist.
 * $variant: 'product' (4:3) | 'promo' (16:9) | 'hero' (tall)
 */
function image_placeholder(string $caption = '', bool $icon = true, string $variant = 'product'): string
{
    $svg = $icon
        ? '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" aria-hidden="true">'
          . '<path d="M20 10 6 18l5 12 7-3v27h28V27l7 3 5-12-14-8c-2 4-6.5 6-12 6s-10-2-12-6z"/></svg>'
        : '';
    $cap = $caption !== '' ? '<span class="placeholder__caption" aria-hidden="true">' . e($caption) . '</span>' : '';

    return '<div class="placeholder placeholder--' . e($variant) . '" role="img" aria-label="' . e($caption) . '">'
         . $svg . $cap . '</div>';
}
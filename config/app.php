<?php
// Global app config + small helpers. Include this first on every page.

date_default_timezone_set('Asia/Colombo');

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

// Sessions: the cookie is HTTP-only, same-site, and "secure" on https
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL !== '' ? BASE_URL . '/' : '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

// True on localhost / 127.0.0.1 (development), false on the live site
$hostName = explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0];
define('IS_LOCAL', PHP_SAPI === 'cli' || in_array($hostName, ['localhost', '127.0.0.1', '::1'], true));

// Site address used in e-mail links, e.g. 'https://your-site.infinityfreeapp.com'. Empty = detect from the request.
define('APP_URL', '');

/**
 * Full path to a page, with ".php" hidden:
 *   url('shop.php?category=Shirts') -> /shop?category=Shirts
 *   url('index.php') or url('/')    -> /
 */
function url(string $path = ''): string
{
    $path = preg_replace('/(^|\/)index\.php(?=$|[?#])/', '$1', $path);
    $path = preg_replace('/\.php(?=$|[?#])/', '', $path);
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

/* ==========================================================================
   Shop helpers
   ========================================================================== */

define('DELIVERY_FEE', 350);   // sample flat delivery fee in LKR (cash on delivery)

// Set to true once order emails are really sent (PHPMailer + SMTP). Until then the
// "Order email" notice on the confirmation page stays hidden, so we never promise an email we don't send.
define('ORDER_EMAILS_ENABLED', false);

// Store contact details shown on the Contact page and the policy pages. Leave empty until the client approves them.
define('STORE_SUPPORT_EMAIL', '');     // e.g. 'support@yourstore.lk'
define('STORE_SUPPORT_PHONE', '');     // e.g. '+94 11 000 0000'

// E-mail sending (password reset links ...). While false, mails are only written to storage/mail.log
// and the live site tells visitors that reset-by-email is not available yet.
define('MAIL_ENABLED', false);
define('MAIL_FROM', 'no-reply@example.com');

/** Friendly product URL:  /product/essential-cotton-tee */
function product_url(string $slug): string
{
    return url('product/' . rawurlencode($slug));
}

/** Swatch colour for a colour name (sample values; the first three come from the design). */
function color_swatch(string $name): string
{
    static $map = [
        'Chalk' => '#f5f3ef',
        'Ink'   => '#20222b',
        'Clay'  => '#d5a798',
        'Sand'  => '#d9c7a6',
        'Stone' => '#b8b4ab',
    ];
    return $map[$name] ?? '#c8cbd7';
}

/** Key of one cart line:  $_SESSION['cart'][ cart_key(...) ] = quantity */
function cart_key(int $productId, string $color, string $size): string
{
    return $productId . '|' . $color . '|' . $size;
}

/** One-time message. flash('cart', 'Added.') stores it; flash('cart') reads and clears it. */
function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

/** CSRF protection for every POST form:  <?= csrf_field() ?>  +  if (!csrf_valid()) {...} */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['csrf'] ?? '';
    return is_string($sent) && hash_equals(csrf_token(), $sent);
}

/* ==========================================================================
   Data + database helpers
   ========================================================================== */

/**
 * All active products from the `products` table, in the same shape the pages already use:
 *   colors -> list, stock -> [ colour => [ size => qty ] ], images -> list, prices -> numbers.
 * Cached for the request. (data/products.php is only the sample data used by `migration.php seed`.)
 */
function all_products(): array
{
    static $products = null;

    if ($products === null) {
        $products = [];
        $rows = db()->query('SELECT * FROM products WHERE is_active = 1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $stock  = json_decode((string) $row['stock'], true);
            $stock  = is_array($stock) ? $stock : [];
            $colors = json_decode((string) $row['colors'], true);
            $images = json_decode((string) $row['images'], true);
            $sale   = $row['sale_price'] !== null ? (float) $row['sale_price'] : 0.0;

            $products[] = [
                'id'          => (int) $row['id'],
                'sku'         => $row['sku'],
                'slug'        => $row['slug'],
                'name'        => $row['name'],
                'category'    => $row['category'],
                'gender'      => $row['gender'],
                'material'    => $row['material'],
                'description' => $row['description'],
                'price'       => (float) $row['price'],
                'sale_price'  => $sale > 0 ? $sale : null,
                'colors'      => is_array($colors) && $colors ? array_values($colors) : array_keys($stock),
                'stock'       => $stock,
                'images'      => is_array($images) ? array_values($images) : [],
            ];
        }
    }
    return $products;
}

/** PDO connection. config/database.php picks database.local.php or database.remote.php. */
function db(): PDO
{
    static $connection = null;

    if ($connection === null) {
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            $connection = $GLOBALS['pdo'];          // already created by an earlier include
        } else {
            require __DIR__ . '/database.php';      // creates $pdo
            $connection = $pdo;
        }

        // Dates (orders.created_at ...) in Sri Lanka time, whatever the database server's own time zone is
        try {
            $connection->exec("SET time_zone = '+05:30'");
        } catch (Throwable $e) {
            // not critical
        }
    }
    return $connection;
}

/** Send the browser to another page and stop. */
function redirect(string $location): void
{
    header('Location: ' . $location);
    exit;
}


/* ==========================================================================
   Toast notifications (rendered by components/toast.php, which footer.php includes)
   ========================================================================== */

/**
 * Queue a toast. It shows on the next page view (or on this one if the footer has not run yet).
 *
 *   toast('Added to bag', 'success', [
 *       'text'         => 'Essential Cotton Tee · Chalk / M',   // optional second line
 *       'action_label' => 'View bag',                           // optional link
 *       'action_url'   => url('cart.php'),
 *   ]);
 *
 * Types: 'success' (default look), 'error', 'info'.
 */
function toast(string $title, string $type = 'info', array $options = []): void
{
    $_SESSION['toasts'][] = [
        'title'        => $title,
        'type'         => in_array($type, ['success', 'error', 'info'], true) ? $type : 'info',
        'text'         => (string) ($options['text'] ?? ''),
        'action_label' => (string) ($options['action_label'] ?? ''),
        'action_url'   => (string) ($options['action_url'] ?? ''),
    ];
}

/** Take (and clear) the queued toasts. */
function toasts_pull(): array
{
    $queued = $_SESSION['toasts'] ?? [];
    unset($_SESSION['toasts']);
    return $queued;
}


/** Full URL (with https://host) for links inside e-mails. */
function absolute_url(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $origin = APP_URL !== '' ? rtrim(APP_URL, '/') : $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $origin . url($path);
}


/** Cheap check (no database): is there a signed-in session? (accounts live in config/auth.php) */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}


/** "1 item" / "3 items" */
function items_label(int $count): string
{
    return $count . ' item' . ($count === 1 ? '' : 's');
}


/** "Delivery charges" -> "delivery-charges" (anchor ids) */
function slugify(string $text): string
{
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
}

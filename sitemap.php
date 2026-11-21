<?php
/**
 * sitemap.xml: the public pages plus every active product.
 * .htaccess maps /sitemap.xml to this file.
 */
require_once __DIR__ . '/config/app.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$xml = static fn (string $v): string => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

$urls = [];
foreach ([
    ['',                   '1.0', 'weekly'],
    ['shop.php',           '0.9', 'daily'],
    ['about.php',          '0.5', 'monthly'],
    ['contact.php',        '0.5', 'monthly'],
    ['size-guide.php',     '0.4', 'monthly'],
    ['delivery-policy.php', '0.3', 'yearly'],
    ['returns-policy.php', '0.3', 'yearly'],
    ['privacy-policy.php', '0.3', 'yearly'],
    ['terms.php',          '0.3', 'yearly'],
] as [$path, $priority, $freq]) {
    $urls[] = ['loc' => absolute_url($path), 'lastmod' => null, 'freq' => $freq, 'priority' => $priority];
}

try {
    foreach (db()->query('SELECT slug, updated_at FROM products WHERE is_active = 1 ORDER BY id') as $row) {
        $time   = strtotime((string) $row['updated_at']);
        $urls[] = [
            'loc'      => absolute_url('product/' . $row['slug']),
            'lastmod'  => $time ? date('c', $time) : null,
            'freq'     => 'weekly',
            'priority' => '0.8',
        ];
    }
} catch (Throwable $e) {
    error_log('sitemap: ' . $e->getMessage());     // the static pages are still listed
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n    <loc>" . $xml($u['loc']) . "</loc>\n";
    if ($u['lastmod']) {
        echo '    <lastmod>' . $xml($u['lastmod']) . "</lastmod>\n";
    }
    echo '    <changefreq>' . $u['freq'] . "</changefreq>\n    <priority>" . $u['priority'] . "</priority>\n  </url>\n";
}
echo "</urlset>\n";

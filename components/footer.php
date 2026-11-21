<?php
/** Closes <main>, outputs the site footer and closes <body>/<html>. */

$footerLinks = [
    [
        'Shop'      => 'shop.php',
        'About'     => 'about.php',
        'Contact'   => 'contact.php',
        'My orders' => 'orders.php',
    ],
    [
        'Size guide'      => 'size-guide.php',
        'Delivery policy' => 'delivery-policy.php',
        'Returns policy'  => 'returns-policy.php',
    ],
    [
        'Privacy policy' => 'privacy-policy.php',
        'Store terms'    => 'terms.php',
    ],
];

// SAMPLE social links: replace each address with the brand's real page once the accounts exist.
// Leave an address empty ('') to hide that icon.
$socialLinks = [
    ['Facebook',  'https://www.facebook.com/dimuthulkonline',  '<path fill="currentColor" stroke="none" d="M13.5 21v-7.5h2.6l.4-3h-3V8.6c0-.9.3-1.5 1.5-1.5h1.6V4.4c-.3 0-1.2-.1-2.3-.1-2.3 0-3.8 1.4-3.8 3.9v2.3H8v3h2.5V21z"/>'],
    ['Instagram', 'https://www.instagram.com/_dimuthu_lk_', '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/>'],
    ['TikTok',    'https://www.tiktok.com/@dimuthulk',   '<path d="M14 3v11.2a3.7 3.7 0 1 1-3.7-3.7"/><path d="M14 3c.4 2.6 2 4.2 4.7 4.5"/>'],
    ['WhatsApp',  'https://wa.me/94768050633',               '<path d="M3.5 20.5l1.2-4.3A8.5 8.5 0 1 1 8 19.4z"/><path d="M9 8.8c.2 2.3 2.4 4.7 5.2 5.4l1-1.1-1.7-.9-.8.6c-.7-.3-1.6-1.2-1.9-1.9l.6-.8-.9-1.7z"/>'],
];
?>
    </main>

    <footer class="site-footer">
        <div class="container footer-grid">
            <div class="footer-brand">
                <p class="footer-logo"><?= e(app_name()) ?></p>
                <p><?= e(APP_TAGLINE) ?></p>
                <ul class="social-links" aria-label="Social media">
                    <?php foreach ($socialLinks as [$label, $href, $icon]): ?>
                    <?php if ($href !== ''): ?>
                    <li>
                        <a href="<?= e($href) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e($label) ?> (opens in a new tab)">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?= $icon ?></svg>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>

            <nav class="footer-links" aria-label="Footer">
                <h2 class="footer-heading">Explore &amp; support</h2>
                <?php foreach ($footerLinks as $group): ?>
                <ul>
                    <?php foreach ($group as $label => $file): ?>
                    <li><a href="<?= url($file) ?>"><?= e($label) ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="container">
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> <?= e(app_name()) ?></p>
                <p>Website development by Gdoop Platforms</p>
            </div>
        </div>
    </footer>

    <?php include __DIR__ . '/toast.php'; ?>

    <div class="cookie-notice" id="cookie-notice" role="region" aria-label="Cookie notice" hidden>
        <p>We only use a small session cookie to keep you signed in and your bag saved. <a href="<?= url('privacy-policy.php') ?>">Privacy policy</a></p>
        <button type="button" class="btn btn--primary" data-cookie-accept>Got it</button>
    </div>

    <script src="<?= asset('js/api.js') ?>" defer></script>
    <script src="<?= asset('js/main.js') ?>" defer></script>
    <script src="<?= asset('js/storefront.js') ?>" defer></script>
    <script src="<?= asset('js/polish.js') ?>" defer></script>
    <script src="<?= asset('js/extras.js') ?>" defer></script>
</body>
</html>
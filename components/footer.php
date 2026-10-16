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
        'Delivery policy' => 'delivery-policy.php',
        'Returns policy'  => 'returns-policy.php',
    ],
    [
        'Privacy policy' => 'privacy-policy.php',
        'Store terms'    => 'terms.php',
    ],
];
?>
    </main>

    <footer class="site-footer">
        <div class="container footer-grid">
            <div class="footer-brand">
                <p class="footer-logo"><?= e(APP_NAME) ?></p>
                <p><?= e(APP_TAGLINE) ?></p>
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
                <p>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?></p>
                <p>Website development by Gdoop Platforms</p>
            </div>
        </div>
    </footer>

    <?php include __DIR__ . '/toast.php'; ?>

    <script src="<?= asset('js/main.js') ?>" defer></script>
</body>
</html>

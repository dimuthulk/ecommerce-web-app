        </main>
    </div>

    <footer class="admin-footer">
        <p><?= !empty($adminBare) ? 'Website development by Gdoop Platforms' : 'Development by Gdoop Platforms &middot; Authenticated administration required' ?></p>
    </footer>

    <?php include __DIR__ . '/toast.php'; ?>

    <script src="<?= asset('js/api.js') ?>"></script>
    <script src="<?= asset('js/main.js') ?>" defer></script>
    <script src="<?= asset('js/admin.js') ?>" defer></script>
    <script src="<?= asset('js/admin-pages.js') ?>" defer></script>
</body>
</html>

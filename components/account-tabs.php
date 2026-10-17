<?php
/**
 * Profile / My orders / Sign out row on the account pages.
 * @var string $activeTab 'profile' or 'orders'
 */
?>
<nav class="account-tabs" aria-label="Account">
    <a class="btn <?= $activeTab === 'profile' ? 'btn--primary' : 'btn--outline' ?>" href="<?= url('account.php') ?>"<?= $activeTab === 'profile' ? ' aria-current="page"' : '' ?>>Profile</a>
    <a class="btn <?= $activeTab === 'orders' ? 'btn--primary' : 'btn--outline' ?>" href="<?= url('orders.php') ?>"<?= $activeTab === 'orders' ? ' aria-current="page"' : '' ?>>My orders</a>
    <form method="post" action="<?= url('logout.php') ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn--ghost">Sign out</button>
    </form>
</nav>

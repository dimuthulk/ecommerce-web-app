<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/cart.php';

/* ---------- 1. Change the bag (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        toast('Your session expired', 'error', ['text' => 'Please try again.']);
        redirect(url('cart.php'));
    }

    $key    = is_string($_POST['key'] ?? null) ? $_POST['key'] : '';
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

    foreach (cart_lines() as $line) {
        if ($line['key'] !== $key) continue;

        if ($action === 'remove') {
            unset($_SESSION['cart'][$key]);
            break;
        }

        $qty = $line['qty'];
        if ($action === 'inc') $qty++;
        if ($action === 'dec') $qty--;
        if ($action === 'set') $qty = (int) ($_POST['qty'] ?? $qty);
        $qty = max(1, $qty);

        if ($line['available'] >= 1 && $qty > $line['available']) {
            $qty = $line['available'];
            toast('Quantity updated', 'info', ['text' => 'Only ' . $line['available'] . ' available in ' . $line['color'] . ' / ' . $line['size'] . '.']);
        }
        $_SESSION['cart'][$key] = $qty;
        break;
    }

    redirect(url('cart.php'));
}

/* ---------- 2. Show the bag ---------- */
cart_prune();
$lines  = cart_lines();
$totals = cart_totals($lines);

$pageTitle       = 'Your bag';
$pageDescription = 'Review the items in your bag.';
require_once __DIR__ . '/components/header.php';
?>

<div class="container page-top">

    <nav class="breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li><a href="<?= url('/') ?>">Home</a></li>
            <li aria-current="page">Cart</li>
        </ol>
    </nav>

    <header class="listing-head">
        <h1>Your bag (<?= $totals['items'] ?>)</h1>
        <p>A good everyday starts here.</p>
    </header>

    <?php if (!$lines): ?>

    <div class="empty-state">
        <h2 class="card-title">Your bag is empty</h2>
        <p>Add something you love and it will show up here.</p>
        <a class="btn btn--primary" href="<?= url('shop.php') ?>">Continue shopping</a>
    </div>

    <?php else: ?>

    <div class="cart-layout">

        <div class="cart-main">
            <section class="card cart-lines" aria-label="Items in your bag">
                <?php foreach ($lines as $line): ?>
                    <?php
                    $product = $line['product'];
                    $link    = product_url($product['slug']);
                    $image   = $product['images'][0] ?? null;
                    $label   = $line['color'] . ' / ' . $line['size'];
                    ?>
                <article class="cart-line">
                    <div class="cart-line__top">
                        <a class="cart-line__image" href="<?= $link ?>" tabindex="-1" aria-hidden="true">
                            <?= $image ? '<img src="' . e(url($image)) . '" alt="">' : image_placeholder($product['sku'], true, 'thumb') ?>
                        </a>
                        <div class="cart-line__info">
                            <h2 class="cart-line__name"><a href="<?= $link ?>"><?= e($product['name']) ?></a></h2>
                            <p class="cart-line__meta"><?= e($label) ?> &middot; Quantity <?= $line['qty'] ?></p>
                            <p class="cart-line__price">
                                <?= e(format_price($line['line_total'])) ?>
                                <?php if ($line['qty'] > 1): ?>
                                    <span class="cart-line__each"><?= e(format_price($line['unit_price'])) ?> each</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <form class="cart-line__controls" method="post" action="<?= url('cart.php') ?>" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="key" value="<?= e($line['key']) ?>">
                        <!-- first submit button = what the Enter key does in the quantity box -->
                        <button type="submit" name="action" value="set" class="sr-only" tabindex="-1">Update quantity</button>

                        <div class="qty">
                            <button type="submit" name="action" value="dec" class="qty__btn" aria-label="Decrease quantity of <?= e($product['name']) ?>"<?= $line['qty'] <= 1 ? ' disabled' : '' ?>>&minus;</button>
                            <input class="qty__input" type="number" name="qty" value="<?= $line['qty'] ?>" min="1" max="<?= max(1, $line['available']) ?>" inputmode="numeric" aria-label="Quantity of <?= e($product['name']) ?>">
                            <button type="submit" name="action" value="inc" class="qty__btn" aria-label="Increase quantity of <?= e($product['name']) ?>"<?= $line['qty'] >= $line['available'] ? ' disabled' : '' ?>>+</button>
                        </div>
                        <button type="submit" name="action" value="remove" class="btn btn--ghost">Remove</button>
                    </form>

                    <?php if ($line['available'] < 1): ?>
                        <p class="cart-line__note cart-line__note--warn"><?= e($label) ?> is sold out. Remove it to continue.</p>
                    <?php elseif (!$line['ok']): ?>
                        <p class="cart-line__note cart-line__note--warn">Only <?= $line['available'] ?> available. Reduce the quantity to continue.</p>
                    <?php else: ?>
                        <p class="cart-line__note"><?= e($label) ?> &middot; <?= $line['available'] ?> available. Stock is checked again at checkout.</p>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </section>

            <a class="btn btn--outline cart-continue" href="<?= url('shop.php') ?>">Continue shopping</a>
        </div>

        <aside class="cart-aside">
            <?php
            $summaryCta = $totals['all_ok']
                ? '<a class="btn btn--primary btn--block" href="' . url('checkout.php') . '">Continue to checkout</a>'
                : '<button type="button" class="btn btn--primary btn--block" disabled>Continue to checkout</button>'
                  . '<p class="form-note">Update the highlighted items to continue.</p>';
            include __DIR__ . '/components/order-summary.php';
            ?>
        </aside>
    </div>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>

<?php
/**
 * "Order summary" card used on the cart and checkout pages.
 *
 * @var array  $totals     from cart_totals()
 * @var string $summaryCta optional, already-escaped HTML shown under the total (e.g. the checkout button)
 */
?>
<section class="card summary" aria-labelledby="summary-title">
    <h2 class="summary__title" id="summary-title">Order summary</h2>

    <dl class="summary__rows">
        <div class="summary__row">
            <dt>Subtotal &middot; <?= e(items_label($totals['items'])) ?></dt>
            <dd><?= e(format_price($totals['subtotal'])) ?></dd>
        </div>
        <div class="summary__row">
            <dt>Delivery</dt>
            <dd><?= e(format_price($totals['delivery'])) ?></dd>
        </div>
        <div class="summary__row summary__row--total">
            <dt>Total</dt>
            <dd><?= e(format_price($totals['total'])) ?></dd>
        </div>
    </dl>

    <?= $summaryCta ?? '' ?>
    <p class="summary__payment">Cash on delivery</p>
</section>

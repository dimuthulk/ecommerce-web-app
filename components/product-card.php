<?php
/**
 * One product card. Expects $product (array, same keys as the products table).
 *
 * @var array $product
 *
 * Use inside a loop:  include __DIR__ . '/components/product-card.php';
 */
$name  = $product['name'];
$color = $product['colors'][0] ?? '';
$meta  = trim($product['category'] . ' / ' . $color, ' /');
$link  = product_url($product['slug']);
$image = $product['images'][0] ?? null;
?>
<article class="product-card">
    <a class="product-card__media" href="<?= $link ?>" tabindex="-1" aria-hidden="true">
        <?php if ($image): ?>
            <img src="<?= e(url($image)) ?>" alt="" loading="lazy">
        <?php else: ?>
            <?= image_placeholder($color !== '' ? $name . ' · ' . $color : $name) ?>
        <?php endif; ?>
    </a>

    <div class="product-card__body">
        <h3 class="product-card__name"><?= e($name) ?></h3>
        <p class="product-card__meta"><?= e($meta) ?></p>
        <p class="product-card__price">
            <?php if (!empty($product['sale_price'])): ?>
                <?= e(format_price((float) $product['sale_price'])) ?>
                <s class="product-card__was"><?= e(format_price((float) $product['price'])) ?></s>
            <?php else: ?>
                <?= e(format_price((float) $product['price'])) ?>
            <?php endif; ?>
        </p>
    </div>

    <a class="btn btn--ghost product-card__cta" href="<?= $link ?>">View product<span class="sr-only">: <?= e($name) ?></span></a>
</article>
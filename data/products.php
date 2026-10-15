<?php
// SAMPLE DATA — placeholder products from the design.
// Keys match the `products` table, so later this file can be replaced by a database query.
// 'images' is empty for now: cards show an image placeholder until real photos are added
// (put a path like 'assets/images/products/essential-cotton-tee.jpg' in the array).

return [
    [
        'id' => 1, 'sku' => 'CB-TEE-CHALK', 'slug' => 'essential-cotton-tee',
        'name' => 'Essential Cotton Tee', 'category' => 'T-shirts', 'gender' => 'unisex',
        'price' => 2400, 'sale_price' => null, 'colors' => ['Chalk'], 'images' => [],
    ],
    [
        'id' => 2, 'sku' => 'CB-SHIRT-SAND', 'slug' => 'relaxed-linen-shirt',
        'name' => 'Relaxed Linen Shirt', 'category' => 'Shirts', 'gender' => 'unisex',
        'price' => 4800, 'sale_price' => null, 'colors' => ['Sand'], 'images' => [],
    ],
    [
        'id' => 3, 'sku' => 'CB-TROUSER-INK', 'slug' => 'everyday-straight-trouser',
        'name' => 'Everyday Straight Trouser', 'category' => 'Trousers', 'gender' => 'unisex',
        'price' => 5600, 'sale_price' => null, 'colors' => ['Ink'], 'images' => [],
    ],
    [
        'id' => 4, 'sku' => 'CB-TANK-CLAY', 'slug' => 'soft-rib-tank',
        'name' => 'Soft Rib Tank', 'category' => 'Tops', 'gender' => 'women',
        'price' => 1900, 'sale_price' => null, 'colors' => ['Clay'], 'images' => [],
    ],
    [
        'id' => 5, 'sku' => 'CB-OVERSHIRT-STONE', 'slug' => 'lightweight-overshirt',
        'name' => 'Lightweight Overshirt', 'category' => 'Layers', 'gender' => 'unisex',
        'price' => 6200, 'sale_price' => null, 'colors' => ['Stone'], 'images' => [],
    ],
    [
        'id' => 6, 'sku' => 'CB-SHORT-INK', 'slug' => 'classic-cotton-short',
        'name' => 'Classic Cotton Short', 'category' => 'Shorts', 'gender' => 'men',
        'price' => 3200, 'sale_price' => null, 'colors' => ['Ink'], 'images' => [],
    ],
];
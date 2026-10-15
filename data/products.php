<?php
// SAMPLE DATA — placeholder products (the first six match the design).
// Keys mirror the `products` table so this file can later be replaced by a database query:
//   colors -> JSON list, stock -> JSON { "size": quantity }, images -> JSON list of paths.
// 'images' is empty for now: cards show an image placeholder until real photos are added.

$row = static fn (
    int $id, string $sku, string $slug, string $name, string $category,
    string $gender, int $price, string $color, array $stock
): array => [
    'id' => $id, 'sku' => $sku, 'slug' => $slug, 'name' => $name,
    'category' => $category, 'gender' => $gender,
    'price' => $price, 'sale_price' => null,
    'colors' => [$color], 'images' => [], 'stock' => $stock,
];

return [
    // --- page 1 (as in the design) ---
    $row(1,  'CB-TEE-CHALK',       'essential-cotton-tee',      'Essential Cotton Tee',      'T-shirts', 'unisex', 2400, 'Chalk', ['XS' => 6, 'S' => 12, 'M' => 20, 'L' => 14, 'XL' => 5]),
    $row(2,  'CB-SHIRT-SAND',      'relaxed-linen-shirt',       'Relaxed Linen Shirt',       'Shirts',   'unisex', 4800, 'Sand',  ['S' => 8, 'M' => 10, 'L' => 7, 'XL' => 0]),
    $row(3,  'CB-TROUSER-INK',     'everyday-straight-trouser', 'Everyday Straight Trouser', 'Trousers', 'unisex', 5600, 'Ink',   ['S' => 5, 'M' => 9, 'L' => 9, 'XL' => 4]),
    $row(4,  'CB-TANK-CLAY',       'soft-rib-tank',             'Soft Rib Tank',             'Tops',     'women',  1900, 'Clay',  ['XS' => 7, 'S' => 11, 'M' => 8]),
    $row(5,  'CB-OVERSHIRT-STONE', 'lightweight-overshirt',     'Lightweight Overshirt',     'Layers',   'unisex', 6200, 'Stone', ['M' => 6, 'L' => 6, 'XL' => 3]),
    $row(6,  'CB-SHORT-INK',       'classic-cotton-short',      'Classic Cotton Short',      'Shorts',   'men',    3200, 'Ink',   ['S' => 10, 'M' => 10, 'L' => 8]),

    // --- page 2 (sample names, not from the design) ---
    $row(7,  'CB-TEE-INK',         'heavyweight-tee',           'Heavyweight Tee',           'T-shirts', 'unisex', 2900, 'Ink',   ['S' => 9, 'M' => 15, 'L' => 11, 'XL' => 6]),
    $row(8,  'CB-SHIRT-CHALK',     'oxford-shirt',              'Oxford Shirt',              'Shirts',   'men',    5200, 'Chalk', ['M' => 7, 'L' => 7, 'XL' => 5]),
    $row(9,  'CB-TROUSER-SAND',    'tapered-chino',             'Tapered Chino',             'Trousers', 'men',    5900, 'Sand',  ['S' => 4, 'M' => 8, 'L' => 8]),
    $row(10, 'CB-TOP-CHALK',       'cropped-tank',              'Cropped Tank',              'Tops',     'women',  1800, 'Chalk', ['XS' => 5, 'S' => 9, 'M' => 9]),
    $row(11, 'CB-LAYER-CLAY',      'knit-cardigan',             'Knit Cardigan',             'Layers',   'women',  7200, 'Clay',  ['S' => 3, 'M' => 5, 'L' => 4]),
    $row(12, 'CB-SHORT-SAND',      'linen-short',               'Linen Short',               'Shorts',   'unisex', 3600, 'Sand',  ['S' => 6, 'M' => 9, 'L' => 6, 'XL' => 0]),

    // --- page 3 ---
    $row(13, 'CB-TEE-SAND',        'pocket-tee',                'Pocket Tee',                'T-shirts', 'unisex', 2600, 'Sand',  ['XS' => 4, 'S' => 8, 'M' => 12, 'L' => 9]),
    $row(14, 'CB-SHIRT-CLAY',      'camp-collar-shirt',         'Camp Collar Shirt',         'Shirts',   'unisex', 4600, 'Clay',  ['S' => 6, 'M' => 8, 'L' => 6]),
    $row(15, 'CB-TROUSER-CHALK',   'wide-leg-trouser',          'Wide Leg Trouser',          'Trousers', 'women',  6400, 'Chalk', ['XS' => 2, 'S' => 5, 'M' => 7, 'L' => 5]),
    $row(16, 'CB-TOP-INK',         'long-sleeve-top',           'Long Sleeve Top',           'Tops',     'unisex', 3100, 'Ink',   ['S' => 7, 'M' => 10, 'L' => 8]),
    $row(17, 'CB-LAYER-INK',       'quilted-vest',              'Quilted Vest',              'Layers',   'unisex', 6800, 'Ink',   ['S' => 0, 'M' => 0, 'L' => 0]),   // sold out (to test the "In stock only" filter)
    $row(18, 'CB-SHORT-CLAY',      'jogger-short',              'Jogger Short',              'Shorts',   'men',    3400, 'Clay',  ['S' => 5, 'M' => 8, 'L' => 8, 'XL' => 3]),
];
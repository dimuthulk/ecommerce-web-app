<?php
// SAMPLE DATA — placeholder products (the first six match the design).
// Keys mirror the `products` table so this file can later be replaced by a database query:
//   stock  -> JSON { "Colour": { "Size": quantity } }   (colours = the keys of stock)
//   images -> JSON list of paths (front, back, fabric, fit). Empty = image placeholders.
// Optional keys (defaults are used when missing): description, details, collection.

$row = static fn (
    int $id, string $sku, string $slug, string $name, string $category,
    string $gender, int $price, array $stock
): array => [
    'id' => $id, 'sku' => $sku, 'slug' => $slug, 'name' => $name,
    'category' => $category, 'gender' => $gender,
    'price' => $price, 'sale_price' => null,
    'colors' => array_keys($stock), 'stock' => $stock, 'images' => [],
];

return [
    // --- page 1 of the shop (as in the design) ---
    $row(1,  'CB-TEE',       'essential-cotton-tee',      'Essential Cotton Tee',      'T-shirts', 'unisex', 2400, [
        'Chalk' => ['XS' => 6, 'S' => 12, 'M' => 5,  'L' => 14, 'XL' => 0],   // XL / Chalk sold out, Chalk / M = 5 (as in the design)
        'Ink'   => ['XS' => 4, 'S' => 9,  'M' => 11, 'L' => 7,  'XL' => 3],
        'Clay'  => ['XS' => 2, 'S' => 6,  'M' => 8,  'L' => 5,  'XL' => 1],
    ]),
    $row(2,  'CB-SHIRT',     'relaxed-linen-shirt',       'Relaxed Linen Shirt',       'Shirts',   'unisex', 4800, [
        'Sand'  => ['S' => 8, 'M' => 10, 'L' => 7, 'XL' => 0],
        'Chalk' => ['S' => 5, 'M' => 6,  'L' => 4, 'XL' => 2],
    ]),
    $row(3,  'CB-TROUSER',   'everyday-straight-trouser', 'Everyday Straight Trouser', 'Trousers', 'unisex', 5600, [
        'Ink'   => ['S' => 5, 'M' => 9, 'L' => 9, 'XL' => 4],
        'Sand'  => ['S' => 3, 'M' => 6, 'L' => 5, 'XL' => 0],
    ]),
    $row(4,  'CB-TANK',      'soft-rib-tank',             'Soft Rib Tank',             'Tops',     'women',  1900, [
        'Clay'  => ['XS' => 7, 'S' => 11, 'M' => 8],
        'Chalk' => ['XS' => 4, 'S' => 6,  'M' => 0],
    ]),
    $row(5,  'CB-OVERSHIRT', 'lightweight-overshirt',     'Lightweight Overshirt',     'Layers',   'unisex', 6200, [
        'Stone' => ['M' => 6, 'L' => 6, 'XL' => 3],
        'Ink'   => ['M' => 4, 'L' => 5, 'XL' => 2],
    ]),
    $row(6,  'CB-SHORT',     'classic-cotton-short',      'Classic Cotton Short',      'Shorts',   'men',    3200, [
        'Ink'   => ['S' => 10, 'M' => 10, 'L' => 8],
        'Sand'  => ['S' => 6,  'M' => 7,  'L' => 5],
    ]),

    // --- page 2 (sample names, not from the design) ---
    $row(7,  'CB-TEE-HW',    'heavyweight-tee',           'Heavyweight Tee',           'T-shirts', 'unisex', 2900, [
        'Ink'   => ['S' => 9, 'M' => 15, 'L' => 11, 'XL' => 6],
    ]),
    $row(8,  'CB-OXFORD',    'oxford-shirt',              'Oxford Shirt',              'Shirts',   'men',    5200, [
        'Chalk' => ['M' => 7, 'L' => 7, 'XL' => 5],
        'Sand'  => ['M' => 3, 'L' => 4, 'XL' => 0],
    ]),
    $row(9,  'CB-CHINO',     'tapered-chino',             'Tapered Chino',             'Trousers', 'men',    5900, [
        'Sand'  => ['S' => 4, 'M' => 8, 'L' => 8],
    ]),
    $row(10, 'CB-CROP',      'cropped-tank',              'Cropped Tank',              'Tops',     'women',  1800, [
        'Chalk' => ['XS' => 5, 'S' => 9, 'M' => 9],
        'Clay'  => ['XS' => 3, 'S' => 5, 'M' => 6],
    ]),
    $row(11, 'CB-CARDIGAN',  'knit-cardigan',             'Knit Cardigan',             'Layers',   'women',  7200, [
        'Clay'  => ['S' => 3, 'M' => 5, 'L' => 4],
    ]),
    $row(12, 'CB-LINEN-SH',  'linen-short',               'Linen Short',               'Shorts',   'unisex', 3600, [
        'Sand'  => ['S' => 6, 'M' => 9, 'L' => 6, 'XL' => 0],
    ]),

    // --- page 3 ---
    $row(13, 'CB-TEE-PKT',   'pocket-tee',                'Pocket Tee',                'T-shirts', 'unisex', 2600, [
        'Sand'  => ['XS' => 4, 'S' => 8, 'M' => 12, 'L' => 9],
        'Chalk' => ['XS' => 2, 'S' => 6, 'M' => 8,  'L' => 5],
    ]),
    $row(14, 'CB-CAMP',      'camp-collar-shirt',         'Camp Collar Shirt',         'Shirts',   'unisex', 4600, [
        'Clay'  => ['S' => 6, 'M' => 8, 'L' => 6],
    ]),
    $row(15, 'CB-WIDE',      'wide-leg-trouser',          'Wide Leg Trouser',          'Trousers', 'women',  6400, [
        'Chalk' => ['XS' => 2, 'S' => 5, 'M' => 7, 'L' => 5],
    ]),
    $row(16, 'CB-LS-TOP',    'long-sleeve-top',           'Long Sleeve Top',           'Tops',     'unisex', 3100, [
        'Ink'   => ['S' => 7, 'M' => 10, 'L' => 8],
    ]),
    $row(17, 'CB-VEST',      'quilted-vest',              'Quilted Vest',              'Layers',   'unisex', 6800, [
        'Ink'   => ['S' => 0, 'M' => 0, 'L' => 0],   // sold out in every size (to test the sold-out state)
    ]),
    $row(18, 'CB-JOGGER',    'jogger-short',              'Jogger Short',              'Shorts',   'men',    3400, [
        'Clay'  => ['S' => 5, 'M' => 8, 'L' => 8, 'XL' => 3],
    ]),
];
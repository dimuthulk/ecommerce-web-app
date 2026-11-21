<?php
/**
 * Policy page content, rendered by components/policy-page.php.
 * Keys match the $policyKey set in terms.php, privacy-policy.php, returns-policy.php, delivery-policy.php.
 * Review the wording with the store owner / a legal adviser before going live.
 */
$fee   = function_exists('delivery_fee') ? format_price(delivery_fee()) : 'a flat fee';
$store = function_exists('app_name') ? app_name() : 'the store';

return [

    'delivery-policy' => [
        'title'     => 'Delivery policy',
        'draft'     => false,
        'effective' => '1 December 2026',
        'version'   => '1.0',
        'sections'  => [
            ['title' => 'Where we deliver', 'paragraphs' => [
                "We deliver to addresses across Sri Lanka through trusted courier partners. Enter your full address, city and a phone number the courier can reach at checkout so your parcel arrives without delays.",
            ]],
            ['title' => 'Delivery charges', 'paragraphs' => [
                "A flat delivery charge of {$fee} applies to every order. The exact amount is shown in your bag and at checkout before you place the order, and there are no hidden extras.",
            ]],
            ['title' => 'Processing and delivery times', 'paragraphs' => [
                "Orders are packed within 1 to 2 working days of confirmation. Delivery usually takes 2 to 4 working days in and around Colombo and 3 to 6 working days elsewhere.",
                "Working days are Monday to Friday, excluding public and bank holidays. Orders placed on a weekend or holiday are processed on the next working day.",
            ]],
            ['title' => 'Tracking your order', 'paragraphs' => [
                "Sign in and open My orders to see the current status of each order, from pending to delivered. We also email you when your order is confirmed.",
            ]],
            ['title' => 'Missed or failed deliveries', 'paragraphs' => [
                "Couriers make up to two delivery attempts. If both fail, the parcel is returned to us and we will contact you to arrange another delivery. Redelivery may carry an additional charge.",
            ]],
            ['title' => 'Damaged or missing parcels', 'paragraphs' => [
                "If your parcel arrives damaged or an item is missing, contact us within 48 hours of delivery with your order number and a photo of the parcel. We will replace the item or refund you.",
            ]],
        ],
    ],

    'returns-policy' => [
        'title'     => 'Returns policy',
        'draft'     => false,
        'effective' => '1 December 2026',
        'version'   => '1.0',
        'sections'  => [
            ['title' => 'Our promise', 'paragraphs' => [
                "If something is not right, you can return it. We want you to be comfortable with every piece you buy from {$store}.",
            ]],
            ['title' => 'Return window', 'paragraphs' => [
                "You can request a return within 7 days of receiving your order. Items must be unworn, unwashed and in their original condition, with all tags attached.",
            ]],
            ['title' => 'What cannot be returned', 'paragraphs' => [
                "For hygiene reasons we cannot accept returns on underwear, socks or any item marked as final sale. Items that show signs of wear, washing, alteration or damage after delivery are also not accepted.",
            ]],
            ['title' => 'How to start a return', 'paragraphs' => [
                "Contact us with your order number, the item you want to return and the reason. We will reply within 2 working days with return instructions and the address to send the parcel to.",
                "Please pack the item securely. We recommend a tracked courier service, as we cannot be responsible for parcels lost on the way back to us.",
            ]],
            ['title' => 'Exchanges', 'paragraphs' => [
                "If the size or colour is not right, you can exchange it for another size or colour of the same product, subject to stock. If the item is unavailable, we will refund you instead.",
            ]],
            ['title' => 'Refunds', 'paragraphs' => [
                "Once your return is received and checked, we process the refund within 5 working days. Online payments are refunded to the original payment method. Orders paid by cash on delivery are refunded by bank transfer.",
                "Delivery charges are refunded only if the item was faulty or we sent the wrong one. Otherwise the return courier cost is paid by the customer.",
            ]],
        ],
    ],

    'privacy-policy' => [
        'title'     => 'Privacy policy',
        'draft'     => false,
        'effective' => '1 December 2026',
        'version'   => '1.0',
        'sections'  => [
            ['title' => 'Who we are', 'paragraphs' => [
                "{$store} is an online clothing store. This policy explains what personal information we collect when you use our website, why we collect it and how we look after it.",
            ]],
            ['title' => 'Information we collect', 'paragraphs' => [
                "Account details such as your name, email address, phone number and saved address. Order details such as the items you bought, delivery address and payment method. Messages you send through our contact form.",
                "We do not store your card number. Online card payments are handled by PayHere, our payment provider, on their own secure pages.",
            ]],
            ['title' => 'How we use your information', 'paragraphs' => [
                "To process and deliver your orders, to send order confirmations and account emails, to answer your questions and to keep the store secure. We do not use your details for advertising without your consent.",
            ]],
            ['title' => 'Who we share it with', 'paragraphs' => [
                "We share only what is needed with delivery couriers (your name, address and phone number) and with PayHere (to take payment). We do not sell your personal information.",
            ]],
            ['title' => 'Cookies', 'paragraphs' => [
                "We use a small session cookie to keep you signed in and to remember the items in your bag. It does not track you across other websites.",
            ]],
            ['title' => 'Your choices', 'paragraphs' => [
                "You can view and update your profile in your account at any time. To ask for a copy of your data or to have your account deleted, contact us and we will respond within 14 days.",
            ]],
            ['title' => 'Keeping your data safe', 'paragraphs' => [
                "Passwords are stored in hashed form, forms are protected against forgery and access to order data is limited to authorised staff. No online service is completely risk free, so please choose a strong, unique password.",
            ]],
        ],
    ],

    'terms' => [
        'title'     => 'Store terms',
        'draft'     => false,
        'effective' => '1 December 2026',
        'version'   => '1.0',
        'sections'  => [
            ['title' => 'Using this website', 'paragraphs' => [
                "By browsing or ordering from {$store} you agree to these terms. Please do not use the site if you do not agree with them. You must be at least 18, or have a parent or guardian's permission, to place an order.",
            ]],
            ['title' => 'Products and pricing', 'paragraphs' => [
                "We aim to show colours, sizes and stock accurately, but screens vary and small differences can occur. Prices are shown in " . (function_exists('currency_code') ? currency_code() : 'LKR') . " and may change without notice. The price you see when you place the order is the price you pay.",
            ]],
            ['title' => 'Placing an order', 'paragraphs' => [
                "Your order is an offer to buy. It is accepted when we confirm it by email or update its status in your account. We may cancel an order if an item is out of stock, a price was shown incorrectly or we cannot verify your details, and we will refund anything you have paid.",
            ]],
            ['title' => 'Payment', 'paragraphs' => [
                "You can pay by cash on delivery or online through PayHere, where available. Online payments are processed securely by PayHere and are subject to their terms.",
            ]],
            ['title' => 'Delivery and returns', 'paragraphs' => [
                "Delivery is covered by our Delivery policy and returns and refunds by our Returns policy. Both form part of these terms.",
            ]],
            ['title' => 'Your account', 'paragraphs' => [
                "You are responsible for keeping your password confidential and for activity under your account. Tell us straight away if you think someone else has used it.",
            ]],
            ['title' => 'Our content', 'paragraphs' => [
                "The photos, text, logos and design of this site belong to {$store} or its licensors. You may not copy or reuse them for commercial purposes without written permission.",
            ]],
            ['title' => 'Liability and changes', 'paragraphs' => [
                "We are not liable for delays or losses caused by events outside our control, such as courier disruption or severe weather. We may update these terms from time to time, and the version shown on this page is the one that applies.",
            ]],
        ],
    ],
];
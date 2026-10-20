<?php
/**
 * The four policy pages. Every policy is "draft" until the client approves the real text.
 *
 * To publish a policy:  set 'draft' => false, fill 'effective' and 'version', and give each
 * section a 'paragraphs' list (the placeholder line is then replaced by the real text).
 */
$draft = ['draft' => true, 'effective' => '[Awaiting approval]', 'version' => 'Draft 0.1'];

return [
    'delivery-policy' => $draft + [
        'title' => 'Delivery policy',
        'sections' => [
            ['title' => 'Delivery coverage',            'placeholder' => '[Approved serviceable areas and eligibility. No geographic coverage is assumed.]'],
            ['title' => 'Delivery charges',             'placeholder' => '[Approved fee rules and calculation. LKR 350 is a sample design assumption only.]'],
            ['title' => 'Processing and delivery timing', 'placeholder' => '[Approved processing rules and delivery estimates. No delivery promises are asserted here.]'],
            ['title' => 'Delivery issues',              'placeholder' => '[Approved procedure for incorrect addresses, failed delivery, loss or damage.]'],
        ],
    ],
    'returns-policy' => $draft + [
        'title' => 'Returns policy',
        'sections' => [
            ['title' => 'Return eligibility',      'placeholder' => '[Approved return window, item condition, exclusions and proof-of-purchase requirements.]'],
            ['title' => 'How to request a return', 'placeholder' => '[Approved customer contact, order-reference requirements and return address.]'],
            ['title' => 'Costs and inspection',    'placeholder' => '[Approved responsibility for shipping costs and inspection process.]'],
            ['title' => 'Resolution and refunds',  'placeholder' => '[Approved refund or replacement process for cash-on-delivery orders and any timelines.]'],
        ],
    ],
    'privacy-policy' => $draft + [
        'title' => 'Privacy policy',
        'sections' => [
            ['title' => 'Information collected',         'placeholder' => '[Client-approved list of account, contact, order and technical data collected.]'],
            ['title' => 'Purpose and lawful handling',   'placeholder' => '[Client-approved purposes, lawful basis and treatment of account and order information.]'],
            ['title' => 'Storage and sharing',           'placeholder' => '[Approved retention periods, service providers, security measures and international processing details.]'],
            ['title' => 'Your choices and contact',      'placeholder' => '[Approved process for access, correction, deletion and privacy questions.]'],
        ],
    ],
    'terms' => $draft + [
        'title' => 'Store terms',
        'sections' => [
            ['title' => 'Store identity and acceptance', 'placeholder' => '[Approved legal store identity, contact details and conditions of using the store.]'],
            ['title' => 'Products, prices and orders',   'placeholder' => '[Approved availability, price accuracy, order acceptance and correction rules.]'],
            ['title' => 'Payment and cancellation',      'placeholder' => '[Approved cash-on-delivery payment obligations and cancellation procedure.]'],
            ['title' => 'Account use and disputes',      'placeholder' => '[Approved account responsibilities, applicable law and dispute process.]'],
        ],
    ],
];

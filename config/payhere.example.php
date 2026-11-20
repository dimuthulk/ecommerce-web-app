<?php
/**
 * PayHere settings. Copy this file to  config/payhere.local.php  (XAMPP) or  config/payhere.remote.php  (live site)
 * and fill it in. Those two files are in .gitignore: they hold the merchant secret, never commit them.
 * Having the file (with both values filled in) is what switches the PayHere option on at checkout.
 *
 * Sandbox: sandbox.payhere.lk -> Integrations -> add your domain -> copy Merchant ID + Merchant Secret.
 * PayHere calls payhere-notify from its own servers, so the site must be reachable from the internet
 * (the live site or a tunnel such as ngrok): a plain http://localhost address cannot receive the callback.
 */
return [
    'merchant_id'     => '',
    'merchant_secret' => '',
    'sandbox'         => true,     // false = real payments on payhere.lk
];

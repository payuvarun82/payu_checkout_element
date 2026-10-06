<?php

declare(strict_types=1);

/**
 * PayU Checkout Elements — UAT config.
 * Prefer environment variables; never put merchant_secret in frontend JS.
 */
return [
    'merchant_key' => getenv('PAYU_MERCHANT_KEY') ?: 'a4vGC2',
    'merchant_secret' => getenv('PAYU_MERCHANT_SECRET') ?: 'hKvGJP28d2ZUuCRz5BnDag58QBdCxBli',
    // Used to verify reverse hash on browser callbacks (payu-s2s-store style)
    'merchant_salt' => getenv('PAYU_MERCHANT_SALT')
        ?: (getenv('PAYU_MERCHANT_SECRET') ?: 'hKvGJP28d2ZUuCRz5BnDag58QBdCxBli'),

    // UAT — switch to api.payu.in / jssdk.payu.in for production
    'checkout_api_url' => getenv('PAYU_CHECKOUT_API_URL') ?: 'https://apitest.payu.in',
    'elements_sdk_url' => getenv('PAYU_ELEMENTS_SDK_URL')
        ?: 'https://jssdk-uat.payu.in/checkout/payu-checkout-elements.umd.js',

    'default_order' => [
        'amount' => (float) (getenv('PAYU_AMOUNT') ?: 100),
        'productinfo' => getenv('PAYU_PRODUCTINFO') ?: 'Tickets',
        'firstname' => getenv('PAYU_FIRSTNAME') ?: 'Sunit',
        'lastname' => getenv('PAYU_LASTNAME') ?: 'Kumar',
        'email' => getenv('PAYU_EMAIL') ?: 'sunit.kumar@mail.com',
        'phone' => getenv('PAYU_PHONE') ?: '9876543210',
        'udf1' => '',
        'udf2' => '',
        'udf5' => '',
    ],

    'additionalPaymentParams' => [
        'authentication_flow' => 'REDIRECT',
    ],

    // Production URLs — applied automatically when env=production is chosen in the UI
    'production' => [
        'checkout_api_url' => 'https://api.payu.in',
        'elements_sdk_url' => 'https://jssdk.payu.in/checkout/payu-checkout-elements.umd.js',
    ],
];

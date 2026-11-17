<?php

declare(strict_types=1);

return [
    // The gateway returns to the "procesando" view of archivum-web, which asks the API for the order (#130), and a
    // cancelled checkout to "Mis pedidos". Neither address changes the payment: only a verified notice does.
    'return_url' => env('PAYMENTS_RETURN_URL', 'http://localhost:5173/pagos/procesando'),
    'cancel_url' => env('PAYMENTS_CANCEL_URL', 'http://localhost:5173/cuenta/pedidos'),
    'timeout_seconds' => 10,
    'connect_timeout_seconds' => 3,
    'onvo' => [
        'secret_key' => env('ONVO_SECRET_KEY', ''),
        'publishable_key' => env('ONVO_PUBLISHABLE_KEY', ''),
        'webhook_secret' => env('ONVO_WEBHOOK_SECRET', ''),
    ],
    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID', ''),
        'client_secret' => env('PAYPAL_CLIENT_SECRET', ''),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID', ''),
        'base_url' => 'https://api-m.sandbox.paypal.com',
    ],
];

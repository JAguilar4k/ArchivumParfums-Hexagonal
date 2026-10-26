<?php

declare(strict_types=1);

return [
    'return_url' => env('PAYMENTS_RETURN_URL', 'http://localhost:8000/api/v1'),
    'cancel_url' => env('PAYMENTS_CANCEL_URL', 'http://localhost:8000/api/v1'),
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

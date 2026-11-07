<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Provider of the perfume recommender
    |--------------------------------------------------------------------------
    |
    | The provider laravel/ai uses when the recommender does not name one, and
    | the model it asks (#117, ADR 0012). Switching to another provider of the
    | SDK means adding its entry from vendor/laravel/ai/config/ai.php below and
    | naming it here; neither the use case nor the domain changes. Its key
    | lives only in .env. The keys of the SDK left out of this file keep their
    | defaults.
    |
    */

    'default' => 'gemini',

    'providers' => [
        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
            'models' => [
                'text' => [
                    'default' => 'gemini-3.5-flash-lite',
                ],
            ],
        ],
    ],

];

<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Demo accounts
    |--------------------------------------------------------------------------
    |
    | Password of the fictitious accounts that php artisan db:seed creates for
    | local development and the class demo. Leave it empty anywhere else, so
    | the demo accounts cannot be seeded there.
    |
    */

    'demo_password' => env('ARCHIVUM_DEMO_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Artificial intelligence
    |--------------------------------------------------------------------------
    |
    | Seconds the perfume recommendation waits for the AI provider before it
    | answers with the rules recommender instead (R50, #117). Fixed for every
    | installation: the settings of each business live in businesses.settings,
    | never here or in the environment.
    |
    */

    'ai_timeout_seconds' => 10,

    /*
    |--------------------------------------------------------------------------
    | Web client
    |--------------------------------------------------------------------------
    |
    | Origin of archivum-web: the recovery link of a password goes there (#193)
    | and config/cors.php accepts only it (#109). Its public address is set
    | with #131.
    |
    */

    'web_origin' => env('ARCHIVUM_WEB_ORIGIN', 'http://localhost:5173'),

    /*
    |--------------------------------------------------------------------------
    | Rate limits of the password
    |--------------------------------------------------------------------------
    |
    | Attempts allowed per window, in minutes, by the named limiters of
    | PasswordsServiceProvider (#193). A limit by email is low because a person
    | asks for few links, and it says nothing about whether the account exists.
    |
    */

    'password_rate_limits' => [
        'change_by_account' => [
            'attempts' => (int) env('ARCHIVUM_RATE_CREDENTIAL_CHANGE_ATTEMPTS', 5),
            'minutes' => (int) env('ARCHIVUM_RATE_CREDENTIAL_CHANGE_MINUTES', 1),
        ],
        'request_by_ip' => [
            'attempts' => (int) env('ARCHIVUM_RATE_RECOVERY_IP_ATTEMPTS', 10),
            'minutes' => (int) env('ARCHIVUM_RATE_RECOVERY_IP_MINUTES', 15),
        ],
        'request_by_email' => [
            'attempts' => (int) env('ARCHIVUM_RATE_RECOVERY_EMAIL_ATTEMPTS', 3),
            'minutes' => (int) env('ARCHIVUM_RATE_RECOVERY_EMAIL_MINUTES', 15),
        ],
        'confirm_by_ip' => [
            'attempts' => (int) env('ARCHIVUM_RATE_RECOVERY_CONFIRM_IP_ATTEMPTS', 10),
            'minutes' => (int) env('ARCHIVUM_RATE_RECOVERY_CONFIRM_IP_MINUTES', 15),
        ],
        'confirm_by_email' => [
            'attempts' => (int) env('ARCHIVUM_RATE_RECOVERY_CONFIRM_EMAIL_ATTEMPTS', 5),
            'minutes' => (int) env('ARCHIVUM_RATE_RECOVERY_CONFIRM_EMAIL_MINUTES', 15),
        ],
    ],

];

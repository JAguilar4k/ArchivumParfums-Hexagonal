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
    | Rate limits
    |--------------------------------------------------------------------------
    |
    | Attempts allowed per window, in minutes, by each named limiter of
    | RateLimitsServiceProvider (#108). The limit by email slows guessing from
    | many addresses; it is high enough, and its window short enough, that a
    | third party cannot easily keep another person out of their account. The
    | webhook limit is generous so it does not stop the retries of a gateway.
    | The limits by email of the password recovery (#193) are low because a
    | person asks for few links, and they say nothing about whether the account
    | exists.
    |
    */

    'rate_limits' => [
        'sign_in_by_ip' => [
            'attempts' => (int) env('ARCHIVUM_RATE_SIGN_IN_IP_ATTEMPTS', 10),
            'minutes' => (int) env('ARCHIVUM_RATE_SIGN_IN_IP_MINUTES', 1),
        ],
        'sign_in_by_email' => [
            'attempts' => (int) env('ARCHIVUM_RATE_SIGN_IN_EMAIL_ATTEMPTS', 20),
            'minutes' => (int) env('ARCHIVUM_RATE_SIGN_IN_EMAIL_MINUTES', 15),
        ],
        'sign_up_by_ip' => [
            'attempts' => (int) env('ARCHIVUM_RATE_SIGN_UP_IP_ATTEMPTS', 10),
            'minutes' => (int) env('ARCHIVUM_RATE_SIGN_UP_IP_MINUTES', 60),
        ],
        'recommendations_by_ip' => [
            'attempts' => (int) env('ARCHIVUM_RATE_RECOMMENDATIONS_IP_ATTEMPTS', 10),
            'minutes' => (int) env('ARCHIVUM_RATE_RECOMMENDATIONS_IP_MINUTES', 1),
        ],
        'recommendations_by_account' => [
            'attempts' => (int) env('ARCHIVUM_RATE_RECOMMENDATIONS_ACCOUNT_ATTEMPTS', 20),
            'minutes' => (int) env('ARCHIVUM_RATE_RECOMMENDATIONS_ACCOUNT_MINUTES', 1),
        ],
        'webhooks_by_ip' => [
            'attempts' => (int) env('ARCHIVUM_RATE_WEBHOOKS_IP_ATTEMPTS', 300),
            'minutes' => (int) env('ARCHIVUM_RATE_WEBHOOKS_IP_MINUTES', 1),
        ],
        'password_change_by_account' => [
            'attempts' => (int) env('ARCHIVUM_RATE_CREDENTIAL_CHANGE_ATTEMPTS', 5),
            'minutes' => (int) env('ARCHIVUM_RATE_CREDENTIAL_CHANGE_MINUTES', 1),
        ],
        'password_reset_by_ip' => [
            'attempts' => (int) env('ARCHIVUM_RATE_RECOVERY_IP_ATTEMPTS', 10),
            'minutes' => (int) env('ARCHIVUM_RATE_RECOVERY_IP_MINUTES', 15),
        ],
        'password_reset_by_email' => [
            'attempts' => (int) env('ARCHIVUM_RATE_RECOVERY_EMAIL_ATTEMPTS', 3),
            'minutes' => (int) env('ARCHIVUM_RATE_RECOVERY_EMAIL_MINUTES', 15),
        ],
        'password_reset_confirmation_by_ip' => [
            'attempts' => (int) env('ARCHIVUM_RATE_RECOVERY_CONFIRM_IP_ATTEMPTS', 10),
            'minutes' => (int) env('ARCHIVUM_RATE_RECOVERY_CONFIRM_IP_MINUTES', 15),
        ],
        'password_reset_confirmation_by_email' => [
            'attempts' => (int) env('ARCHIVUM_RATE_RECOVERY_CONFIRM_EMAIL_ATTEMPTS', 5),
            'minutes' => (int) env('ARCHIVUM_RATE_RECOVERY_CONFIRM_EMAIL_MINUTES', 15),
        ],
    ],

];

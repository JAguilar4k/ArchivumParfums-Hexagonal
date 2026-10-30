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
    | Privacy notice
    |--------------------------------------------------------------------------
    |
    | Version of the notice that archivum-web shows. POST /api/v1/cuentas keeps
    | it with the date a customer accepts it (#110). Change it when the text
    | of the notice changes.
    |
    */

    'privacy_notice_version' => env('ARCHIVUM_PRIVACY_NOTICE_VERSION', '2026-10'),

];

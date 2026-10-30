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
    | Origin of archivum-web, the only one config/cors.php accepts (#109). Its
    | public address is set with #131.
    |
    */

    'web_origin' => env('ARCHIVUM_WEB_ORIGIN', 'http://localhost:5173'),

];

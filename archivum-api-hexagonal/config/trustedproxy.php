<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Addresses of the proxy of the platform, separated by commas, so the API
    | recognises HTTPS and the IP of the client behind it (#21). Empty trusts
    | nobody: a client cannot pretend with an X-Forwarded-* header of its own.
    |
    */

    'proxies' => env('ARCHIVUM_TRUSTED_PROXIES'),

];

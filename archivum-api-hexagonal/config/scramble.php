<?php

declare(strict_types=1);

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Dedoc\Scramble\OpenApiVersion;

return [

    /*
    |--------------------------------------------------------------------------
    | Documented routes
    |--------------------------------------------------------------------------
    |
    | Only the routes under /api/v1 are part of the contract. The documentation
    | pages themselves live at /docs/api and are not documented.
    |
    */

    'api_path' => 'api/v1',

    'api_domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Versioned contract
    |--------------------------------------------------------------------------
    |
    | `composer openapi` writes the contract here. The file is committed, and a
    | test fails when the code generates something different.
    |
    */

    'export_path' => 'openapi/v1.json',

    'openapi_version' => OpenApiVersion::V3_1,

    'info' => [
        /*
         * Version of the contract. It is a literal, not an environment variable,
         * so that every machine generates the same file.
         */
        'version' => '1.0.0',

        'description' => 'API REST de ArchivumParfums: citas de prueba de olores, pedidos bajo pedido, decants y pagos para perfumerías. '
            .'Las rutas y los atributos JSON están en español; los errores siguen el RFC 9457 (`application/problem+json`).',
    ],

    'ui' => [
        'title' => 'ArchivumParfums · API v1',
    ],

    /*
     * The documentation is gated by the `viewApiDocs` gate, defined in
     * Src\Shared\Infrastructure\OpenApi\ApiContract.
     */
    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],

    /*
     * The security requirements are documented by ApiContract, which marks the
     * routes behind `auth:sanctum` and leaves the public ones without any.
     */
    'security_strategy' => null,
];

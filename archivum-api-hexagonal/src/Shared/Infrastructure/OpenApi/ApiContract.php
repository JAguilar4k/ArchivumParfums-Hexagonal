<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\OpenApi;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Server;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** Configure how Scramble describes the API: security, errors and who can read the documentation. */
final class ApiContract
{
    /** Name of the security scheme in the contract. */
    public const string SECURITY_SCHEME = 'sanctum';

    /** Middleware that makes a route require the Sanctum token. */
    private const string AUTH_MIDDLEWARE = 'auth:sanctum';

    /** Base path of the API, relative so that the contract does not depend on APP_URL. */
    private const string SERVER_URL = '/api/v1';

    /** Register the gate and the transformers of the contract. */
    public static function configure(): void
    {
        // Outside production anyone can read the documentation. In production nobody can
        // until the monitoring panel (#180) decides how credentials are asked for.
        Gate::define('viewApiDocs', fn ($user = null): bool => ! app()->isProduction());

        Scramble::configure()
            ->withOperationTransformers(self::documentSecurity(...))
            ->withOperationTransformers(OptionalFields::document(...))
            ->withDocumentTransformers(self::documentContract(...))
            ->withDocumentTransformers(ProblemResponses::class);
    }

    /** Require the token on the routes behind `auth:sanctum`; the public ones declare no security. */
    private static function documentSecurity(Operation $operation, RouteInfo $routeInfo): void
    {
        $isProtected = collect($routeInfo->route->gatherMiddleware())
            ->contains(fn (mixed $middleware): bool => is_string($middleware) && Str::is(self::AUTH_MIDDLEWARE, $middleware));

        if ($isProtected) {
            $operation->addSecurity(new SecurityRequirement(self::SECURITY_SCHEME));
        }
    }

    /** Declare the Sanctum scheme and the relative server. */
    private static function documentContract(OpenApi $document): void
    {
        $document->components->addSecurityScheme(
            self::SECURITY_SCHEME,
            SecurityScheme::http('bearer')->setDescription(
                'Token de Sanctum que entrega `POST /sesiones`. Se envía como `Authorization: Bearer <token>`.',
            ),
        );

        $document->servers = [Server::make(self::SERVER_URL)];
    }
}

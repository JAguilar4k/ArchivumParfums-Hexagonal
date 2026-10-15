<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;
use Src\Accounts\Sessions\Application\UseCases\FindAccountRole;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provisional check of the role groups until the abilities and policies of #102 and #104: role:owner keeps a
 * route for the owners of the business and role:customer for the accounts without a row in members.
 */
final readonly class RequireAccountRole
{
    /** Receive the authentication and the use case that answers the role of an account. */
    public function __construct(
        private Auth $auth,
        private FindAccountRole $findRole,
    ) {}

    /**
     * Let the request through only when the authenticated account has one of the roles; any other is forbidden
     * before the controller runs, so nothing is written.
     *
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userId = $this->auth->guard()->id() ?? throw new AuthenticationException;

        if (! in_array($this->findRole->execute((string) $userId)->value, $roles, true)) {
            throw new AccessDeniedHttpException;
        }

        return $next($request);
    }
}

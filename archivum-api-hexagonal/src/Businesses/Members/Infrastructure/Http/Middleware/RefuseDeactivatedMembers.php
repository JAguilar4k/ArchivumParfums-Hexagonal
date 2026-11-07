<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\Accounts\Sessions\Domain\Exceptions\InvalidCredentialsException;
use Src\Businesses\Members\Application\UseCases\IsAccountDeactivated;
use Symfony\Component\HttpFoundation\Response;

final readonly class RefuseDeactivatedMembers
{
    /** Receive the use case that recognises a deactivated person. */
    public function __construct(private IsAccountDeactivated $isDeactivated) {}

    /**
     * Answer a deactivated person as any wrong credentials, without saying that the account exists. Without this,
     * an account with no active membership would sign in as a customer.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $email = $request->input('correo');

        if (is_string($email) && $this->isDeactivated->execute($email)) {
            throw InvalidCredentialsException::create();
        }

        return $next($request);
    }
}

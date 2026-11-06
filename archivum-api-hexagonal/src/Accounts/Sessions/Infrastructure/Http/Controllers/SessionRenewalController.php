<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Infrastructure\Http\Controllers;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Accounts\Sessions\Application\UseCases\RenewSession;
use Src\Accounts\Sessions\Domain\Exceptions\SessionNotRenewableException;
use Src\Accounts\Sessions\Infrastructure\Http\Resources\SessionTokenResource;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;

final readonly class SessionRenewalController
{
    /**
     * Swap the token of the request, while it is still valid, for a new one with its expiration; the old one stops
     * working. A token that expired or was revoked meanwhile gets the same 401 as a missing token: sign in again.
     *
     * @throws AuthenticationException
     */
    public function store(Request $request, RenewSession $renewSession): JsonResponse
    {
        /** @var UserModel $user */
        $user = $request->user();

        try {
            $session = $renewSession->execute($user->id, (string) $user->currentAccessToken()->getKey());
        } catch (SessionNotRenewableException) {
            throw new AuthenticationException;
        }

        return new SessionTokenResource($session)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}

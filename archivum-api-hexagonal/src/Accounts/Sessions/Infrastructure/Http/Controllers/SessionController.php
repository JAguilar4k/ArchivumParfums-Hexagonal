<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Accounts\Sessions\Application\UseCases\EndSession;
use Src\Accounts\Sessions\Application\UseCases\StartSession;
use Src\Accounts\Sessions\Infrastructure\Http\Requests\StoreSession;
use Src\Accounts\Sessions\Infrastructure\Http\Resources\SessionToken;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;

final readonly class SessionController
{
    /** Sign in with email and password and answer a bearer token. */
    public function store(StoreSession $request, StartSession $startSession): JsonResponse
    {
        return new SessionToken($startSession->execute($request->toCommand()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /** Sign out by revoking the token of the request. */
    public function destroy(Request $request, EndSession $endSession): Response
    {
        /** @var UserModel $user */
        $user = $request->user();

        $endSession->execute((string) $user->currentAccessToken()->getKey());

        return response()->noContent();
    }
}

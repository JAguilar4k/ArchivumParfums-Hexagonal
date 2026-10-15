<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Accounts\Sessions\Application\UseCases\StartSession;
use Src\Accounts\Sessions\Infrastructure\Http\Requests\StoreSession;
use Src\Accounts\Sessions\Infrastructure\Http\Resources\SessionToken;

final readonly class SessionController
{
    /** Sign in with email and password and answer a bearer token. */
    public function store(StoreSession $request, StartSession $startSession): JsonResponse
    {
        return new SessionToken($startSession->execute($request->toCommand()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}

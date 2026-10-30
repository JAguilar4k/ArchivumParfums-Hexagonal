<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Src\Accounts\Sessions\Application\DTOs\CurrentAccount;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Accounts\Sessions\Infrastructure\Http\Resources\CurrentAccountResource;
use Src\Accounts\Users\Application\UseCases\RegisterCustomer;
use Src\Accounts\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Accounts\Users\Infrastructure\Http\Requests\StoreAccountRequest;

final readonly class AccountController
{
    /**
     * Register a customer and answer its public data. It does not sign in: the client calls POST /sesiones next.
     *
     * @throws ValidationException
     */
    public function store(StoreAccountRequest $request, RegisterCustomer $registerCustomer): JsonResponse
    {
        try {
            $user = $registerCustomer->execute($request->toCommand());
        } catch (EmailAlreadyRegisteredException $exception) {
            throw ValidationException::withMessages(['correo' => __($exception->translationKey())]);
        }

        // A new account has no row in members, so it is always a customer without a business.
        $account = new CurrentAccount($user->id->value, $user->name->value, $user->email->value, AccountRole::Customer, null);

        return new CurrentAccountResource($account)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('accounts.current.show'));
    }
}

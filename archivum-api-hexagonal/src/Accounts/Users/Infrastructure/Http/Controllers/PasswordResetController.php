<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Src\Accounts\Users\Application\UseCases\ResetPassword;
use Src\Accounts\Users\Domain\Exceptions\InvalidResetTokenException;
use Src\Accounts\Users\Infrastructure\Http\Requests\ConfirmPasswordReset;
use Src\Accounts\Users\Infrastructure\Http\Requests\StorePasswordReset;
use Src\Accounts\Users\Infrastructure\Http\Resources\PasswordResetRequestedResource;
use Src\Accounts\Users\Infrastructure\Queue\SendPasswordResetLink;

final readonly class PasswordResetController
{
    /**
     * Queue the recovery for every email and answer the same: the search of the account, the token and the mail all
     * happen outside the request, so neither the body nor the time tell whether the account exists.
     */
    public function store(StorePasswordReset $request): JsonResponse
    {
        SendPasswordResetLink::dispatch($request->string('correo')->toString());

        return new PasswordResetRequestedResource(null)
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    /**
     * Set the new password. A token that expired, was used or belongs to another email answers 422 in its field.
     *
     * @throws ValidationException
     */
    public function confirm(ConfirmPasswordReset $request, ResetPassword $resetPassword): Response
    {
        try {
            $resetPassword->execute($request->toCommand());
        } catch (InvalidResetTokenException $exception) {
            throw ValidationException::withMessages(['token' => __($exception->translationKey())]);
        }

        return response()->noContent();
    }
}

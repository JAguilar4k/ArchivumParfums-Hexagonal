<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Src\Accounts\Users\Application\DTOs\ChangePasswordCommand;
use Src\Accounts\Users\Application\UseCases\ChangePassword;
use Src\Accounts\Users\Domain\Exceptions\InvalidCurrentPasswordException;
use Src\Accounts\Users\Infrastructure\Http\Requests\UpdatePassword;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;

final readonly class PasswordController
{
    /**
     * Change the password of the account of the session. A wrong current password answers 422 in its field.
     *
     * @throws ValidationException
     */
    public function update(UpdatePassword $request, ChangePassword $changePassword): Response
    {
        /** @var UserModel $user */
        $user = $request->user();

        try {
            $changePassword->execute(new ChangePasswordCommand(
                $user->id,
                (string) $user->currentAccessToken()->getKey(),
                $request->string('contrasena_actual')->toString(),
                $request->string('contrasena_nueva')->toString(),
            ));
        } catch (InvalidCurrentPasswordException $exception) {
            throw ValidationException::withMessages(['contrasena_actual' => __($exception->translationKey())]);
        }

        return response()->noContent();
    }
}

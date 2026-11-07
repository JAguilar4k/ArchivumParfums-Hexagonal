<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Src\Accounts\Users\Application\DTOs\ResetPasswordCommand;
use Src\Accounts\Users\Domain\ValueObjects\Email;

final class ConfirmPasswordReset extends FormRequest
{
    /**
     * Validation rules for the confirmation of a recovery; the new password follows the policy of a sign-up.
     *
     * @return array<string, list<string|Password>>
     */
    public function rules(): array
    {
        return [
            'correo' => ['required', 'string', 'email:filter', 'max:'.Email::MAX_LENGTH],
            'token' => ['required', 'string'],
            'contrasena_nueva' => ['required', 'string', Password::defaults()],
        ];
    }

    /** Map the validated payload to the use case input. */
    public function toCommand(): ResetPasswordCommand
    {
        return new ResetPasswordCommand(
            $this->string('correo')->toString(),
            $this->string('token')->toString(),
            $this->string('contrasena_nueva')->toString(),
        );
    }
}

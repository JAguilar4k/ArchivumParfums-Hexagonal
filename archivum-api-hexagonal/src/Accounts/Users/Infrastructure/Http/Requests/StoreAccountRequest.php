<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Src\Accounts\Users\Application\DTOs\RegisterAccountCommand;
use Src\Accounts\Users\Domain\ValueObjects\Email;
use Src\Accounts\Users\Domain\ValueObjects\UserName;

final class StoreAccountRequest extends FormRequest
{
    /**
     * Validation rules for the only data a sign-up asks for (Ley 8968). Any other field, such as a role or a business,
     * is left out of the command and ignored.
     *
     * @return array<string, list<string|Password>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:'.UserName::MAX_LENGTH],
            'correo' => ['required', 'string', 'email:filter', 'max:'.Email::MAX_LENGTH],
            'contrasena' => ['required', 'string', Password::defaults()],
            'acepta_privacidad' => ['required', 'boolean', 'accepted'],
        ];
    }

    /** Map the validated payload to the use case input. */
    public function toCommand(): RegisterAccountCommand
    {
        return new RegisterAccountCommand(
            $this->string('nombre')->toString(),
            $this->string('correo')->toString(),
            $this->string('contrasena')->toString(),
        );
    }
}

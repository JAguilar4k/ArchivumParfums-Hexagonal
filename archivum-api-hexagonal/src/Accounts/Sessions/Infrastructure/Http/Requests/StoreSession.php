<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Accounts\Sessions\Application\DTOs\StartSessionCommand;
use Src\Accounts\Users\Domain\ValueObjects\Email;

final class StoreSession extends FormRequest
{
    /**
     * Validation rules for the credentials.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'correo' => ['required', 'string', 'email:filter', 'max:'.Email::MAX_LENGTH],
            'contrasena' => ['required', 'string'],
        ];
    }

    /** Map the validated payload to the use case input. */
    public function toCommand(): StartSessionCommand
    {
        return new StartSessionCommand(
            $this->string('correo')->toString(),
            $this->string('contrasena')->toString(),
        );
    }
}

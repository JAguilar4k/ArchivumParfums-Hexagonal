<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Accounts\Users\Domain\ValueObjects\Email;

final class StorePasswordReset extends FormRequest
{
    /**
     * Validation rules for a request of recovery.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'correo' => ['required', 'string', 'email:filter', 'max:'.Email::MAX_LENGTH],
        ];
    }
}

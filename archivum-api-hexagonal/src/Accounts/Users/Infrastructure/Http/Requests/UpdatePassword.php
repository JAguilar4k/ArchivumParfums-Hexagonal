<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class UpdatePassword extends FormRequest
{
    /**
     * Validation rules for a change of password; the new one follows the policy of a sign-up.
     *
     * @return array<string, list<string|Password>>
     */
    public function rules(): array
    {
        return [
            'contrasena_actual' => ['required', 'string'],
            'contrasena_nueva' => ['required', 'string', Password::defaults()],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Src\Accounts\Users\Domain\ValueObjects\Email;
use Src\Accounts\Users\Domain\ValueObjects\UserName;
use Src\Businesses\Members\Application\DTOs\RegisterMemberCommand;
use Src\Shared\Domain\Contracts\BusinessContext;

final class StoreMemberRequest extends FormRequest
{
    /**
     * Validation rules for a new person of the staff. Any other field, such as a business, is ignored.
     *
     * @return array<string, list<string|Enum|Password>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:'.UserName::MAX_LENGTH],
            'correo' => ['required', 'string', 'email:filter', 'max:'.Email::MAX_LENGTH],
            'contrasena' => ['required', 'string', Password::defaults()],
            'rol' => ['required', 'string', Rule::enum(MemberRoleName::class)],
            'atiende_citas' => ['sometimes', 'boolean'],
        ];
    }

    /** Map the validated payload to the use case input; the business comes from the session, never from the payload. */
    public function toCommand(BusinessContext $businessContext): RegisterMemberCommand
    {
        return new RegisterMemberCommand(
            $businessContext->current()->value,
            $this->string('nombre')->toString(),
            $this->string('correo')->toString(),
            $this->string('contrasena')->toString(),
            MemberRoleName::from($this->string('rol')->toString())->toDomain(),
            $this->boolean('atiende_citas'),
        );
    }
}

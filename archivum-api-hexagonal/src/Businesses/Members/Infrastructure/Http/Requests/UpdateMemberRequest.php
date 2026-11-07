<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Src\Accounts\Users\Domain\ValueObjects\UserName;
use Src\Businesses\Members\Application\DTOs\UpdateMemberCommand;
use Src\Shared\Domain\Contracts\BusinessContext;

final class UpdateMemberRequest extends FormRequest
{
    /**
     * Validation rules for a partial change of a person of the staff. The email is not editable here.
     *
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:'.UserName::MAX_LENGTH],
            'rol' => ['sometimes', 'required', 'string', Rule::enum(MemberRoleName::class)],
            'atiende_citas' => ['sometimes', 'required', 'boolean'],
            'activo' => ['sometimes', 'required', 'boolean'],
        ];
    }

    /** Map the validated payload to the use case input; the business comes from the session, never from the payload. */
    public function toCommand(BusinessContext $businessContext, string $memberId): UpdateMemberCommand
    {
        return new UpdateMemberCommand(
            $businessContext->current()->value,
            $memberId,
            $this->has('nombre') ? $this->string('nombre')->toString() : null,
            $this->has('rol') ? MemberRoleName::from($this->string('rol')->toString())->toDomain() : null,
            $this->has('atiende_citas') ? $this->boolean('atiende_citas') : null,
            $this->has('activo') ? $this->boolean('activo') : null,
        );
    }
}

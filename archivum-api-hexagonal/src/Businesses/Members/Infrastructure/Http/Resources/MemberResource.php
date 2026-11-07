<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Businesses\Members\Application\DTOs\MemberProfile;
use Src\Businesses\Members\Infrastructure\Http\Requests\MemberRoleName;

/**
 * @property-read MemberProfile $resource
 */
final class MemberResource extends JsonResource
{
    /** Wrap what the panel shows of a person of the staff. */
    public function __construct(private readonly MemberProfile $member)
    {
        parent::__construct($member);
    }

    /**
     * Expose the person with the vocabulary of the business.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->member->id,
            'nombre' => $this->member->name,
            'correo' => $this->member->email,
            'rol' => MemberRoleName::fromDomain($this->member->role)->value,
            'atiende_citas' => $this->member->attendsAppointments,
            'activo' => $this->member->isActive,
        ];
    }
}

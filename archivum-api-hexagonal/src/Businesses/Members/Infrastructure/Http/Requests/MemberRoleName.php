<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Infrastructure\Http\Requests;

use Src\Businesses\Members\Domain\ValueObjects\MemberRole;

/** Names of the roles of the staff in the API, as GET /api/v1/cuenta names them. */
enum MemberRoleName: string
{
    case Owner = 'dueno';
    case Staff = 'colaborador';

    /** The role of the domain with this name. */
    public function toDomain(): MemberRole
    {
        return match ($this) {
            self::Owner => MemberRole::Owner,
            self::Staff => MemberRole::Staff,
        };
    }

    /** The name of a role of the domain. */
    public static function fromDomain(MemberRole $role): self
    {
        return match ($role) {
            MemberRole::Owner => self::Owner,
            MemberRole::Staff => self::Staff,
        };
    }
}

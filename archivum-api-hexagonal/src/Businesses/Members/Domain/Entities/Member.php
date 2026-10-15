<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Domain\Entities;

use Src\Businesses\Members\Domain\ValueObjects\MemberRole;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class Member
{
    private function __construct(
        public UserId $userId,
        public BusinessId $businessId,
        public MemberRole $role,
        public bool $attendsAppointments,
    ) {}

    /** Link an account to one business with a role; owners and staff can both attend appointments. */
    public static function create(
        UserId $userId,
        BusinessId $businessId,
        MemberRole $role,
        bool $attendsAppointments = false,
    ): self {
        return new self($userId, $businessId, $role, $attendsAppointments);
    }

    /** Rebuild a member already stored. */
    public static function reconstitute(
        UserId $userId,
        BusinessId $businessId,
        MemberRole $role,
        bool $attendsAppointments,
    ): self {
        return new self($userId, $businessId, $role, $attendsAppointments);
    }

    /** Whether this member owns the business. */
    public function isOwner(): bool
    {
        return $this->role === MemberRole::Owner;
    }
}

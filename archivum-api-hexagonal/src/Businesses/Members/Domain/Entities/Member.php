<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Domain\Entities;

use Src\Businesses\Members\Domain\ValueObjects\MemberRole;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\MemberId;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class Member
{
    /** Hold the full state of a member of the staff. */
    private function __construct(
        public MemberId $id,
        public UserId $userId,
        public BusinessId $businessId,
        public MemberRole $role,
        public bool $attendsAppointments,
        public bool $isActive = true,
    ) {}

    /** Link an account to one business with a role; owners and staff can both attend appointments. */
    public static function create(
        MemberId $id,
        UserId $userId,
        BusinessId $businessId,
        MemberRole $role,
        bool $attendsAppointments = false,
    ): self {
        return new self($id, $userId, $businessId, $role, $attendsAppointments);
    }

    /** Rebuild a member already stored. */
    public static function reconstitute(
        MemberId $id,
        UserId $userId,
        BusinessId $businessId,
        MemberRole $role,
        bool $attendsAppointments,
        bool $isActive = true,
    ): self {
        return new self($id, $userId, $businessId, $role, $attendsAppointments, $isActive);
    }

    /** The same member with the changes given; null keeps the current value. */
    public function update(?MemberRole $role, ?bool $attendsAppointments, ?bool $isActive): self
    {
        return new self(
            $this->id,
            $this->userId,
            $this->businessId,
            $role ?? $this->role,
            $attendsAppointments ?? $this->attendsAppointments,
            $isActive ?? $this->isActive,
        );
    }

    /** Whether this member is an owner who can still sign in. */
    public function isActiveOwner(): bool
    {
        return $this->isActive && $this->isOwner();
    }

    /** Whether this member owns the business. */
    public function isOwner(): bool
    {
        return $this->role === MemberRole::Owner;
    }
}

<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Application\DTOs;

use Src\Businesses\Members\Domain\ValueObjects\MemberRole;

final readonly class UpdateMemberCommand
{
    /** Carry the business of the session, the person and the changes sent; null keeps the current value. */
    public function __construct(
        public string $businessId,
        public string $memberId,
        public ?string $name,
        public ?MemberRole $role,
        public ?bool $attendsAppointments,
        public ?bool $isActive,
    ) {}
}

<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Application\DTOs;

use Src\Businesses\Members\Domain\ValueObjects\MemberRole;

final readonly class AddMemberCommand
{
    /** Carry the account, the business and the role of a new member of the staff. */
    public function __construct(
        public string $userId,
        public string $businessId,
        public MemberRole $role,
        public bool $attendsAppointments,
    ) {}
}

<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Application\DTOs;

use Src\Businesses\Members\Domain\ValueObjects\MemberRole;

final readonly class MemberProfile
{
    /** Carry what the panel shows of a person of the staff. */
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public MemberRole $role,
        public bool $attendsAppointments,
        public bool $isActive,
    ) {}
}

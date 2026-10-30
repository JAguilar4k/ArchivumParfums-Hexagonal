<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Application\DTOs;

use SensitiveParameter;
use Src\Businesses\Members\Domain\ValueObjects\MemberRole;

final readonly class RegisterMemberCommand
{
    /** Carry the business of the session and the data of the new person; the owner sets the initial password. */
    public function __construct(
        public string $businessId,
        public string $name,
        public string $email,
        #[SensitiveParameter] public string $password,
        public MemberRole $role,
        public bool $attendsAppointments,
    ) {}
}

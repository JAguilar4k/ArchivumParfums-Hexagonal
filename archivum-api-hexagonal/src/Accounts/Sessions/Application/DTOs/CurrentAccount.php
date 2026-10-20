<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Application\DTOs;

use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;

final readonly class CurrentAccount
{
    /** Carry the data of the signed-in account, with its business when it belongs to a staff. */
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public AccountRole $role,
        public ?StaffMembership $membership,
    ) {}
}

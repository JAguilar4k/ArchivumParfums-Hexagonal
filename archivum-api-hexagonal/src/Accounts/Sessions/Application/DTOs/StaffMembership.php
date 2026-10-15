<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Application\DTOs;

use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;

final readonly class StaffMembership
{
    /** Carry the role of a person of the staff and the business they belong to. */
    public function __construct(
        public AccountRole $role,
        public string $businessId,
        public string $businessName,
        public string $businessSlug,
    ) {}
}

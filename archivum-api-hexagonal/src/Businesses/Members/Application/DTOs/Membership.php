<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Application\DTOs;

use Src\Businesses\Members\Domain\ValueObjects\MemberRole;

final readonly class Membership
{
    /** Carry the business and the role of an account on its staff: what is known of it before any business is pinned. */
    public function __construct(
        public string $businessId,
        public MemberRole $role,
    ) {}
}

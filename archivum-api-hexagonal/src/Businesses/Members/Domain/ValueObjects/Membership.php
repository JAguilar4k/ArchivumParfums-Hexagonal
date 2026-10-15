<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Domain\ValueObjects;

use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class Membership
{
    /** Hold the business and the role of an account on its staff: what is known of it before any business is pinned. */
    public function __construct(
        public BusinessId $businessId,
        public MemberRole $role,
    ) {}
}

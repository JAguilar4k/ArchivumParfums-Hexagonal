<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Domain\Contracts;

use Src\Accounts\Users\Domain\ValueObjects\CustomerResourceId;
use Src\Accounts\Users\Domain\ValueObjects\CustomerResourceType;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\UserId;

interface CustomerBusinesses
{
    /**
     * Find the businesses where an account is a customer, before any business is pinned.
     *
     * @return list<BusinessId>
     */
    public function of(UserId $userId): array;

    /** Find the business of a resource of the customer; null when it belongs to another customer or does not exist. */
    public function ofResource(CustomerResourceType $type, CustomerResourceId $resourceId, UserId $userId): ?BusinessId;
}

<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use Src\Accounts\Users\Domain\Contracts\CustomerBusinesses;
use Src\Accounts\Users\Domain\ValueObjects\CustomerResourceId;
use Src\Accounts\Users\Domain\ValueObjects\CustomerResourceType;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\UserId;

final class InMemoryCustomerBusinesses implements CustomerBusinesses
{
    /** @var array<string, array{string, string, BusinessId}> resource identifier => [type, account, business] */
    private array $resources = [];

    /** Record a resource of a customer in a business. */
    public function add(CustomerResourceType $type, string $resourceId, string $userId, BusinessId $businessId): void
    {
        $this->resources[$resourceId] = [$type->value, $userId, $businessId];
    }

    /**
     * Businesses of the resources of the account, once each.
     *
     * @return list<BusinessId>
     */
    public function of(UserId $userId): array
    {
        $businesses = [];

        foreach ($this->resources as [, $owner, $businessId]) {
            if ($owner === $userId->value) {
                $businesses[$businessId->value] = $businessId;
            }
        }

        return array_values($businesses);
    }

    /** Business of a resource of the same type and account. */
    public function ofResource(CustomerResourceType $type, CustomerResourceId $resourceId, UserId $userId): ?BusinessId
    {
        [$storedType, $owner, $businessId] = $this->resources[$resourceId->value] ?? [null, null, null];

        return $storedType === $type->value && $owner === $userId->value ? $businessId : null;
    }
}

<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure\Persistence\Queries;

use Illuminate\Support\Facades\DB;
use Src\Accounts\Users\Domain\Contracts\CustomerBusinesses;
use Src\Accounts\Users\Domain\ValueObjects\CustomerResourceId;
use Src\Accounts\Users\Domain\ValueObjects\CustomerResourceType;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class PostgresCustomerBusinesses implements CustomerBusinesses
{
    /**
     * Ask fn_customer_businesses, which reads customers before any business is pinned.
     *
     * @return list<BusinessId>
     */
    public function of(UserId $userId): array
    {
        $businesses = [];

        foreach (DB::select('select fn_customer_businesses(?) as business_id', [$userId->value]) as $row) {
            $businesses[] = new BusinessId($row->business_id);
        }

        return $businesses;
    }

    /** Ask fn_customer_resource_business, which only answers for a resource of the same account. */
    public function ofResource(CustomerResourceType $type, CustomerResourceId $resourceId, UserId $userId): ?BusinessId
    {
        $businessId = DB::scalar(
            'select fn_customer_resource_business(?, ?, ?)',
            [$type->value, $resourceId->value, $userId->value],
        );

        return is_string($businessId) ? new BusinessId($businessId) : null;
    }
}

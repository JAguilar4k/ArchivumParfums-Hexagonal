<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Accounts;

use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Decants\Packs\Application\Contracts\PackCustomers;
use Src\Shared\Domain\Contracts\BusinessContext;

/** The customer records of the Accounts module, as the purchase of a pack needs them. */
final readonly class AccountsPackCustomers implements PackCustomers
{
    /** Receive the business of the address. */
    public function __construct(private BusinessContext $business) {}

    /**
     * firstOrCreate inserts in a savepoint and reads the row again if the unique key (business_id, user_id) refuses
     * it, so two first purchases at once end with one customer.
     */
    public function findOrCreate(string $userId): string
    {
        return CustomerModel::query()->firstOrCreate(['business_id' => $this->business->current()->value, 'user_id' => $userId])->id;
    }
}

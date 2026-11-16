<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\Contracts;

use Src\Scheduling\Appointments\Domain\ValueObjects\CustomerId;

/** Port of the customer record of a store: the account that books becomes a customer of that store if it was not. */
interface AppointmentCustomers
{
    /** The customer record of the account in the store of the context, created the first time it books. */
    public function findOrCreate(string $userId): CustomerId;

    /**
     * The customer record of the store of the context with that identity, or null when it is of another store or does
     * not exist: the panel only books for the customers of its own store.
     */
    public function ofStore(string $customerId): ?CustomerId;
}

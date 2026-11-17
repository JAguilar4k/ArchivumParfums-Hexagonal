<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\Contracts;

use Src\Scheduling\Appointments\Domain\ValueObjects\CustomerId;

/** Port of the customer record of a store: the account that books becomes a customer of that store if it was not. */
interface AppointmentCustomers
{
    /** The customer record of the account in the store of the context, created the first time it books. */
    public function findOrCreate(string $userId): CustomerId;
}

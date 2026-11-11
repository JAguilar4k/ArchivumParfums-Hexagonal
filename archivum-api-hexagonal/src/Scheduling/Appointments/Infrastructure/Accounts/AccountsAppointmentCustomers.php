<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Accounts;

use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Scheduling\Appointments\Application\Contracts\AppointmentCustomers;
use Src\Scheduling\Appointments\Domain\ValueObjects\CustomerId;
use Src\Shared\Domain\Contracts\BusinessContext;

/** Adapter that finds, or creates, the customer record of the account in the store of the context. */
final readonly class AccountsAppointmentCustomers implements AppointmentCustomers
{
    /** Take the business the customer record belongs to. */
    public function __construct(private BusinessContext $business) {}

    public function findOrCreate(string $userId): CustomerId
    {
        $customer = CustomerModel::query()->firstOrCreate([
            'business_id' => $this->business->current()->value,
            'user_id' => $userId,
        ]);

        return new CustomerId((string) $customer->id);
    }
}

<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Infrastructure\Accounts;

use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Sales\Orders\Application\Contracts\OrderCustomers;
use Src\Shared\Domain\Contracts\BusinessContext;

final readonly class AccountsOrderCustomers implements OrderCustomers
{
    public function __construct(private BusinessContext $business) {}

    public function findOrCreate(string $userId): string
    {
        return CustomerModel::query()->firstOrCreate(['business_id' => $this->business->current()->value, 'user_id' => $userId])->id;
    }

    public function owns(string $customerId, string $userId): bool
    {
        return CustomerModel::query()->where('business_id', $this->business->current()->value)->where('id', $customerId)->where('user_id', $userId)->exists();
    }
}

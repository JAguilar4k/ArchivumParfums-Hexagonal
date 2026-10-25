<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\Contracts;

interface OrderCustomers
{
    public function findOrCreate(string $userId): string;

    public function owns(string $customerId, string $userId): bool;
}

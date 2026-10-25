<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\Contracts;

use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Domain\ValueObjects\OrderStatus;

interface OrderSearch
{
    /** @return list<Order> */
    public function all(?string $userId = null, ?OrderStatus $status = null): array;
}

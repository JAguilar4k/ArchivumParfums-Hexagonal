<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\UseCases;

use Src\Sales\Orders\Application\Contracts\OrderSearch;
use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Domain\ValueObjects\OrderStatus;

final readonly class ListBusinessOrders
{
    public function __construct(private OrderSearch $search) {}

    /** @return list<Order> */
    public function execute(?string $status): array
    {
        return $this->search->all(status: $status === null ? null : OrderStatus::fromSpanish($status));
    }
}

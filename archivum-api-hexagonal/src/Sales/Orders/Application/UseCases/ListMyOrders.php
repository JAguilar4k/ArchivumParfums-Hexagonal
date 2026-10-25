<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\UseCases;

use Src\Sales\Orders\Application\Contracts\OrderSearch;
use Src\Sales\Orders\Domain\Entities\Order;

final readonly class ListMyOrders
{
    public function __construct(private OrderSearch $search) {}

    /** @return list<Order> */
    public function execute(string $userId): array
    {
        return $this->search->all($userId);
    }
}

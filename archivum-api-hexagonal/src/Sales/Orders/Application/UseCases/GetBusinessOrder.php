<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\UseCases;

use Src\Sales\Orders\Domain\Contracts\OrderRepository;
use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Domain\Exceptions\OrderNotFoundException;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;

final readonly class GetBusinessOrder
{
    public function __construct(private OrderRepository $orders) {}

    public function execute(string $orderId): Order
    {
        return $this->orders->find(new OrderId($orderId)) ?? throw new OrderNotFoundException('orders.not_found');
    }
}

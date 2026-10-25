<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\UseCases;

use Src\Sales\Orders\Application\Contracts\OrderCustomers;
use Src\Sales\Orders\Domain\Contracts\OrderRepository;
use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Domain\Exceptions\OrderNotFoundException;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;

final readonly class GetMyOrder
{
    public function __construct(private OrderRepository $orders, private OrderCustomers $customers) {}

    public function execute(string $orderId, string $userId): Order
    {
        $order = $this->orders->find(new OrderId($orderId)) ?? throw new OrderNotFoundException('orders.not_found');

        if (! $this->customers->owns($order->customerId, $userId)) {
            throw new OrderNotFoundException('orders.not_found');
        }

        return $order;
    }
}

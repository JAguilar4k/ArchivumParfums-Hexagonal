<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\UseCases;

use Src\Sales\Orders\Domain\Contracts\OrderRepository;
use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Domain\Exceptions\OrderNotFoundException;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class MarkOrderReady
{
    public function __construct(private OrderRepository $orders, private TransactionRunner $transaction) {}

    public function execute(string $orderId): Order
    {
        return $this->transaction->run(function () use ($orderId): Order {
            $order = $this->orders->find(new OrderId($orderId), true) ?? throw new OrderNotFoundException('orders.not_found');
            $order->markReady();
            $this->orders->save($order);

            return $order;
        });
    }
}

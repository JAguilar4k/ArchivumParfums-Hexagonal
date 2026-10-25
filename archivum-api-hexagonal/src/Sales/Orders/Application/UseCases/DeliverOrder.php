<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\UseCases;

use Src\Sales\Orders\Application\Contracts\RefundRequests;
use Src\Sales\Orders\Domain\Contracts\OrderRepository;
use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Domain\Exceptions\OrderConflictException;
use Src\Sales\Orders\Domain\Exceptions\OrderNotFoundException;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class DeliverOrder
{
    public function __construct(private OrderRepository $orders, private TransactionRunner $transaction, private RefundRequests $refunds) {}

    public function execute(string $orderId): Order
    {
        return $this->transaction->run(function () use ($orderId): Order {
            $order = $this->orders->find(new OrderId($orderId), true) ?? throw new OrderNotFoundException('orders.not_found');

            if ($this->refunds->hasPendingRefunds($orderId)) {
                throw new OrderConflictException('orders.refund_pending');
            }

            $order->deliver();
            $this->orders->save($order);

            return $order;
        });
    }
}

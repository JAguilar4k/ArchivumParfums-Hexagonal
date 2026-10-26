<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\UseCases;

use DateTimeImmutable;
use Src\Sales\Orders\Application\Contracts\RefundRequests;
use Src\Sales\Orders\Domain\Contracts\OrderRepository;
use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Domain\Exceptions\OrderNotFoundException;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;
use Src\Sales\Orders\Domain\ValueObjects\OrderStatus;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class MarkOrderLineUnavailable
{
    public function __construct(private OrderRepository $orders, private RefundRequests $refunds, private TransactionRunner $transaction) {}

    public function execute(string $orderId, string $lineId, string $unavailableAt): Order
    {
        return $this->transaction->run(function () use ($orderId, $lineId, $unavailableAt): Order {
            $order = $this->orders->find(new OrderId($orderId), true) ?? throw new OrderNotFoundException('orders.not_found');
            $refund = $order->markLineUnavailable($lineId, new DateTimeImmutable($unavailableAt), $this->refunds->grossPaid($orderId));

            if ($order->status === OrderStatus::Cancelled) {
                $refund = $this->refunds->remaining($orderId);
            }

            if ($refund > 0) {
                $this->refunds->request($orderId, $refund, $lineId);
            }

            $this->orders->save($order);

            return $order;
        });
    }
}

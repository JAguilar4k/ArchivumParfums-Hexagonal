<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\UseCases;

use DateTimeImmutable;
use Src\Sales\Orders\Application\Contracts\DecantStock;
use Src\Sales\Orders\Application\Contracts\OrderCustomers;
use Src\Sales\Orders\Application\Contracts\RefundRequests;
use Src\Sales\Orders\Domain\Contracts\OrderRepository;
use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Domain\Exceptions\OrderNotFoundException;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;
use Src\Sales\Orders\Domain\ValueObjects\OrderLineStatus;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class CancelMyOrder
{
    public function __construct(private OrderRepository $orders, private OrderCustomers $customers, private DecantStock $stock, private RefundRequests $refunds, private TransactionRunner $transaction) {}

    public function execute(string $orderId, string $userId, string $cancelledAt): Order
    {
        return $this->transaction->run(function () use ($orderId, $userId, $cancelledAt): Order {
            $order = $this->orders->find(new OrderId($orderId), true) ?? throw new OrderNotFoundException('orders.not_found');

            if (! $this->customers->owns($order->customerId, $userId)) {
                throw new OrderNotFoundException('orders.not_found');
            }

            $order->cancel(new DateTimeImmutable($cancelledAt));

            foreach ($order->lines as $line) {
                if ($line->status === OrderLineStatus::Reserved && $line->decantBottleId !== null && $line->ml !== null) {
                    $this->stock->release($line->decantBottleId, $line->ml, $line->id, $userId);
                    $line->advanceTo(OrderLineStatus::Released);
                }
            }

            if ($order->amounts->paid > 0) {
                $this->refunds->request($order->id->value, $order->amounts->paid);
            }

            $this->orders->save($order);

            return $order;
        });
    }
}

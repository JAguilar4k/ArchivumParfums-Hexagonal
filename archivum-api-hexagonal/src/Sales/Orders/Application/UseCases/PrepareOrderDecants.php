<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\UseCases;

use Src\Sales\Orders\Application\Contracts\DecantStock;
use Src\Sales\Orders\Domain\Contracts\OrderRepository;
use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Domain\Exceptions\OrderNotFoundException;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class PrepareOrderDecants
{
    public function __construct(private OrderRepository $orders, private DecantStock $stock, private TransactionRunner $transaction) {}

    /**
     * Prepare every reserved decant of a confirmed order, consuming the ml its reservation kept (R26). A decant paid
     * with a pack is consumed like the others, and its movement shows the redemption (R8).
     */
    public function execute(string $orderId, string $userId): Order
    {
        return $this->transaction->run(function () use ($orderId, $userId): Order {
            $order = $this->orders->find(new OrderId($orderId), true) ?? throw new OrderNotFoundException('orders.not_found');

            foreach ($order->decantsToPrepare() as $line) {
                if ($line->decantBottleId !== null && $line->ml !== null) {
                    $this->stock->consume($line->decantBottleId, $line->ml, $line->id, $userId);
                }
            }

            $order->prepareDecants();
            $this->orders->save($order);

            return $order;
        });
    }
}

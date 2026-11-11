<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\UseCases;

use DateTimeImmutable;
use Src\Sales\Orders\Application\Contracts\DecantStock;
use Src\Sales\Orders\Application\Contracts\OrderPayments;
use Src\Sales\Orders\Application\DTOs\ExpireUnpaidOrderCommand;
use Src\Sales\Orders\Domain\Contracts\OrderRepository;
use Src\Sales\Orders\Domain\Exceptions\OrderConflictException;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;
use Src\Sales\Orders\Domain\ValueObjects\OrderLineStatus;
use Src\Sales\Orders\Domain\ValueObjects\OrderStatus;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class ExpireUnpaidOrder
{
    /** Receive the order repository, the ml of the bottles, the payments of the order and the transaction port. */
    public function __construct(
        private OrderRepository $orders,
        private DecantStock $stock,
        private OrderPayments $payments,
        private TransactionRunner $transaction,
    ) {}

    /**
     * When the deadline of an order arrives (R25): if it still waits for its payment, it expires, its pending payment
     * expires with it and each reserved decant gives its ml back to the bottle, all in one transaction. An order that
     * was paid, cancelled or already expired does not change, so a second run releases nothing. Answer whether the
     * order expired.
     */
    public function execute(ExpireUnpaidOrderCommand $command): bool
    {
        return $this->transaction->run(function () use ($command): bool {
            // The lock makes a confirmation of its payment (#94) wait for this transaction, or this one wait for it.
            $order = $this->orders->find(new OrderId($command->orderId), true);
            $checkedAt = new DateTimeImmutable($command->checkedAt);

            if ($order === null || ! $order->isOverdueAt($checkedAt)) {
                return false;
            }

            $order->expire($checkedAt);

            foreach ($order->lines as $line) {
                if ($line->status === OrderLineStatus::Reserved && $line->decantBottleId !== null && $line->ml !== null) {
                    $this->stock->releaseExpired($line->decantBottleId, $line->ml, $line->id);
                    $line->advanceTo(OrderLineStatus::Released);
                }
            }

            $this->payments->expirePending($order->id->value);

            // Besides the lock, the row changes only while it still waits for its payment. If another operation changed
            // it first, the transaction is undone, together with the ml it gave back and the payment it closed.
            if (! $this->orders->saveIfStatus($order, OrderStatus::PendingPayment)) {
                throw new OrderConflictException('orders.transition');
            }

            return true;
        });
    }
}

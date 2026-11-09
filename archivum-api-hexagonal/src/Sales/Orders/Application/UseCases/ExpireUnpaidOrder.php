<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\UseCases;

use DateTimeImmutable;
use Src\Sales\Orders\Application\Contracts\DecantPackRedemption;
use Src\Sales\Orders\Application\Contracts\DecantStock;
use Src\Sales\Orders\Application\Contracts\OrderPayments;
use Src\Sales\Orders\Application\DTOs\ExpireUnpaidOrderCommand;
use Src\Sales\Orders\Domain\Contracts\OrderRepository;
use Src\Sales\Orders\Domain\Entities\OrderLine;
use Src\Sales\Orders\Domain\Exceptions\OrderConflictException;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;
use Src\Sales\Orders\Domain\ValueObjects\OrderLineStatus;
use Src\Sales\Orders\Domain\ValueObjects\OrderStatus;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class ExpireUnpaidOrder
{
    /** Receive the order repository, the ml of the bottles, the packs of its redeemed decants, the payments of the order and the transaction port. */
    public function __construct(
        private OrderRepository $orders,
        private DecantStock $stock,
        private DecantPackRedemption $packs,
        private OrderPayments $payments,
        private TransactionRunner $transaction,
    ) {}

    /**
     * When the deadline of an order arrives (R25): if it still waits for its payment, it expires, its pending payment
     * expires with it, each reserved decant gives its ml back to the bottle and a decant paid with a pack goes back to
     * the pack, all in one transaction (precisions of R32–R35). The packs are locked before the bottles, each pack and
     * each bottle in the order of its identifier, as when the order was placed or cancelled. An order that was paid,
     * cancelled or already expired does not change, so a second run releases nothing. Answer whether the order expired.
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

            $redeemed = array_filter($order->lines, fn (OrderLine $line): bool => $line->status === OrderLineStatus::Reserved && $line->decantPackId !== null);
            usort($redeemed, fn (OrderLine $a, OrderLine $b): int => strcmp((string) $a->decantPackId, (string) $b->decantPackId));
            foreach ($redeemed as $line) {
                $this->packs->giveBack($line, $checkedAt);
            }

            $byBottle = $order->lines;
            usort($byBottle, fn (OrderLine $a, OrderLine $b): int => strcmp((string) $a->decantBottleId, (string) $b->decantBottleId));
            foreach ($byBottle as $line) {
                if ($line->status === OrderLineStatus::Reserved && $line->decantBottleId !== null && $line->ml !== null) {
                    $this->stock->releaseExpired($line->decantBottleId, $line->ml, $line->id);
                    $line->advanceTo(OrderLineStatus::Released);
                }
            }

            $this->payments->expirePending($order->id->value);

            // Besides the lock, the row changes only while it still waits for its payment. If another operation changed
            // it first, the transaction is undone, together with the decants and the ml it gave back and the payment it closed.
            if (! $this->orders->saveIfStatus($order, OrderStatus::PendingPayment)) {
                throw new OrderConflictException('orders.transition');
            }

            return true;
        });
    }
}

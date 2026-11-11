<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\UseCases;

use DateTimeImmutable;
use Src\Sales\Orders\Application\Contracts\DecantPackRedemption;
use Src\Sales\Orders\Application\Contracts\DecantStock;
use Src\Sales\Orders\Application\Contracts\OrderCustomers;
use Src\Sales\Orders\Application\Contracts\OrderDeadlines;
use Src\Sales\Orders\Application\Contracts\OrderProducts;
use Src\Sales\Orders\Application\DTOs\PlaceOrderCommand;
use Src\Sales\Orders\Domain\Contracts\OrderRepository;
use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Domain\Entities\OrderLine;
use Src\Sales\Orders\Domain\ValueObjects\OrderLineKind;
use Src\Sales\Orders\Domain\ValueObjects\OrderStatus;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class PlaceOrder
{
    public function __construct(private OrderRepository $orders, private OrderProducts $products, private OrderCustomers $customers, private DecantStock $stock, private DecantPackRedemption $packs, private OrderDeadlines $deadlines, private BusinessContext $business, private TransactionRunner $transaction) {}

    /**
     * Place and reserve every line atomically; a shortage rolls back all earlier reservations. A decant paid with a
     * pack takes one decant of the pack and reserves its ml like any other decant, which its preparation consumes
     * (R25, R26, R34); the order does not charge it (R24). The packs are locked before the lines that name them are
     * saved and before the bottles, each pack in the order of its identifier, so two orders never wait for each other.
     * An order that waits for its payment is checked again at its deadline, when it expires if it is still unpaid
     * (R25).
     */
    public function execute(PlaceOrderCommand $command): Order
    {
        return $this->transaction->run(function () use ($command): Order {
            $lines = [];
            $quotes = [];
            $settings = $this->products->settings();

            foreach ($command->lines as $index => $input) {
                $pack = $input['pack'] ?? null;
                $quotes[$index] = $quote = $this->products->quote($input['kind'], $input['reference'], $input['ml'], $index, $pack !== null);
                $kind = OrderLineKind::from($input['kind']);
                $lines[] = OrderLine::create($this->orders->nextLineId(), $kind, $quote['variant_id'], $kind === OrderLineKind::Decant ? $input['reference'] : null, $input['ml'], $input['quantity'], $quote['price'], $pack);
            }

            $at = new DateTimeImmutable($command->placedAt);
            $order = Order::create($this->orders->nextId(), $this->business->current(), $this->customers->findOrCreate($command->userId), $lines, $at, $at->modify('+'.$settings['expiry_minutes'].' minutes'), $settings['deposit_percent']);

            $redeemed = array_filter($order->lines, fn (OrderLine $line): bool => $line->decantPackId !== null);
            uasort($redeemed, fn (OrderLine $a, OrderLine $b): int => strcmp((string) $a->decantPackId, (string) $b->decantPackId));
            foreach ($redeemed as $index => $line) {
                $this->packs->redeem($line, $order->customerId, $quotes[$index]['sale_price'], $quotes[$index]['size_ml'], $at, $index);
            }

            $this->orders->save($order);
            foreach ($redeemed as $line) {
                $this->packs->record($line);
            }

            foreach ($order->lines as $line) {
                if ($line->decantBottleId !== null && $line->ml !== null) {
                    $this->stock->reserve($line->decantBottleId, $line->ml, $line->id, $command->userId);
                }
            }

            if ($order->status === OrderStatus::PendingPayment) {
                $this->deadlines->checkAt($order->id, $order->businessId, $order->expiresAt);
            }

            return $order;
        });
    }
}

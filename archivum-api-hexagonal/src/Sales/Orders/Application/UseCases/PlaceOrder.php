<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\UseCases;

use DateTimeImmutable;
use Src\Sales\Orders\Application\Contracts\DecantStock;
use Src\Sales\Orders\Application\Contracts\OrderCustomers;
use Src\Sales\Orders\Application\Contracts\OrderProducts;
use Src\Sales\Orders\Application\DTOs\PlaceOrderCommand;
use Src\Sales\Orders\Domain\Contracts\OrderRepository;
use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Domain\Entities\OrderLine;
use Src\Sales\Orders\Domain\ValueObjects\OrderLineKind;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class PlaceOrder
{
    public function __construct(private OrderRepository $orders, private OrderProducts $products, private OrderCustomers $customers, private DecantStock $stock, private BusinessContext $business, private TransactionRunner $transaction) {}

    /** Place and reserve every line atomically; a shortage rolls back all earlier reservations. */
    public function execute(PlaceOrderCommand $command): Order
    {
        return $this->transaction->run(function () use ($command): Order {
            $lines = [];
            $settings = $this->products->settings();

            foreach ($command->lines as $index => $input) {
                $quote = $this->products->quote($input['kind'], $input['reference'], $input['ml'], $index);
                $kind = OrderLineKind::from($input['kind']);
                $lines[] = OrderLine::create($this->orders->nextLineId(), $kind, $quote['variant_id'], $kind === OrderLineKind::Decant ? $input['reference'] : null, $input['ml'], $input['quantity'], $quote['price']);
            }

            $at = new DateTimeImmutable($command->placedAt);
            $order = Order::create($this->orders->nextId(), $this->business->current(), $this->customers->findOrCreate($command->userId), $lines, $at, $at->modify('+'.$settings['expiry_minutes'].' minutes'), $settings['deposit_percent']);
            $this->orders->save($order);

            foreach ($order->lines as $line) {
                if ($line->decantBottleId !== null && $line->ml !== null) {
                    $this->stock->reserve($line->decantBottleId, $line->ml, $line->id, $command->userId);
                }
            }

            return $order;
        });
    }
}

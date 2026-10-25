<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Domain\Entities;

use DateTimeImmutable;
use Src\Sales\Orders\Domain\Exceptions\OrderConflictException;
use Src\Sales\Orders\Domain\Exceptions\OrderNotFoundException;
use Src\Sales\Orders\Domain\ValueObjects\OrderAmounts;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;
use Src\Sales\Orders\Domain\ValueObjects\OrderLineKind;
use Src\Sales\Orders\Domain\ValueObjects\OrderLineStatus;
use Src\Sales\Orders\Domain\ValueObjects\OrderStatus;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;

final class Order
{
    /** @param list<OrderLine> $lines */
    private function __construct(
        public readonly OrderId $id,
        public readonly BusinessId $businessId,
        public readonly string $customerId,
        public readonly array $lines,
        public private(set) OrderStatus $status,
        public private(set) OrderAmounts $amounts,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $expiresAt,
        public private(set) ?DateTimeImmutable $cancelledAt,
        public readonly ?string $deliveryMethod,
        public readonly int $depositPercent,
        public readonly ?string $customerName = null,
        public readonly ?string $businessName = null,
    ) {}

    /** @param list<OrderLine> $lines */
    public static function create(OrderId $id, BusinessId $businessId, string $customerId, array $lines, DateTimeImmutable $createdAt, DateTimeImmutable $expiresAt, int $depositPercent = 50): self
    {
        if ($lines === []) {
            throw new InvalidValueException('orders.empty');
        }

        $amounts = OrderAmounts::calculate($lines, depositPercent: $depositPercent);

        return new self($id, $businessId, $customerId, $lines, $amounts->dueNow === 0 ? OrderStatus::Confirmed : OrderStatus::PendingPayment, $amounts, $createdAt, $expiresAt, null, null, $depositPercent);
    }

    /** @param list<OrderLine> $lines */
    public static function reconstitute(OrderId $id, BusinessId $businessId, string $customerId, array $lines, OrderStatus $status, OrderAmounts $amounts, DateTimeImmutable $createdAt, DateTimeImmutable $expiresAt, ?DateTimeImmutable $cancelledAt = null, ?string $deliveryMethod = null, int $depositPercent = 50, ?string $customerName = null, ?string $businessName = null): self
    {
        return new self($id, $businessId, $customerId, $lines, $status, $amounts, $createdAt, $expiresAt, $cancelledAt, $deliveryMethod, $depositPercent, $customerName, $businessName);
    }

    /** Credit only a payment that the provider confirmed in the server-side flow. */
    public function recordPayment(int $amount): void
    {
        if (! in_array($this->status, [OrderStatus::PendingPayment, OrderStatus::Ready], true) || $amount <= 0 || $this->amounts->paid + $amount > $this->amounts->subtotal) {
            throw new OrderConflictException('orders.payment_not_allowed');
        }

        $this->amounts = OrderAmounts::calculate($this->lines, $this->amounts->paid + $amount, $this->depositPercent);

        if ($this->status === OrderStatus::PendingPayment && $this->amounts->paid >= $this->amounts->dueNow) {
            $this->status = OrderStatus::Confirmed;
        }
    }

    /** Record a late provider approval for refunding, keeping the order and its released lines closed. */
    public function recordLatePayment(int $amount): void
    {
        if (! in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Expired], true)
            || $amount <= 0 || $amount > Colones::MAX - $this->amounts->paid) {
            throw new OrderConflictException('orders.payment_not_allowed');
        }

        $this->amounts = OrderAmounts::calculate($this->lines, $this->amounts->paid + $amount, $this->depositPercent);
    }

    /** Deduct actual returned money only after a provider refund succeeds. */
    public function recordRefund(int $amount): void
    {
        if ($amount <= 0 || $amount > $this->amounts->paid) {
            throw new OrderConflictException('orders.payment_not_allowed');
        }

        $this->amounts = OrderAmounts::calculate($this->lines, $this->amounts->paid - $amount, $this->depositPercent);
    }

    /** Cancel before purchasing or preparing; refunds are requested durably by the use case. */
    public function cancel(DateTimeImmutable $at): void
    {
        if (! in_array($this->status, [OrderStatus::PendingPayment, OrderStatus::Confirmed], true) || $this->hasPreparedDecants()) {
            throw new OrderConflictException('orders.cannot_cancel');
        }

        $this->status = OrderStatus::Cancelled;
        $this->cancelledAt = $at;
    }

    /** Expire only an unpaid pending order whose deadline elapsed. */
    public function expire(DateTimeImmutable $at): void
    {
        if ($this->status !== OrderStatus::PendingPayment || $at < $this->expiresAt) {
            throw new OrderConflictException('orders.transition');
        }

        $this->status = OrderStatus::Expired;
    }

    /** The purchase-order module uses this after placing every pending bottle. */
    public function markOrdered(): void
    {
        if ($this->status !== OrderStatus::Confirmed || ! $this->hasBottles()) {
            throw new OrderConflictException('orders.transition');
        }

        foreach ($this->lines as $line) {
            if ($line->kind === OrderLineKind::Bottle && $line->status === OrderLineStatus::Pending) {
                $line->advanceTo(OrderLineStatus::Ordered);
            }
        }

        $this->status = OrderStatus::Ordered;
    }

    /** @return list<OrderLine> */
    public function decantsToPrepare(): array
    {
        if (! in_array($this->status, [OrderStatus::Confirmed, OrderStatus::Ordered], true)) {
            throw new OrderConflictException('orders.cannot_prepare');
        }

        $lines = array_values(array_filter($this->lines, fn (OrderLine $line): bool => $line->status === OrderLineStatus::Reserved));

        if ($lines === []) {
            throw new OrderConflictException('orders.no_decants');
        }

        return $lines;
    }

    /** Prepare every reserved decant after the stock adapter consumed its reservation. */
    public function prepareDecants(): void
    {
        foreach ($this->decantsToPrepare() as $line) {
            $line->advanceTo(OrderLineStatus::Prepared);
        }

        if (! $this->hasBottles()) {
            $this->status = OrderStatus::Ready;
        }
    }

    /** Mark ready only after the purchase and decant preparation both finished. */
    public function markReady(): void
    {
        if ($this->status !== OrderStatus::Ordered) {
            throw new OrderConflictException('orders.transition');
        }

        foreach ($this->lines as $line) {
            if (! in_array($line->status, [OrderLineStatus::Received, OrderLineStatus::Unavailable, OrderLineStatus::Prepared], true)) {
                throw new OrderConflictException('orders.not_ready');
            }
        }

        $this->status = OrderStatus::Ready;
    }

    /** The order leaves the store only after its complete amount was paid. */
    public function deliver(): void
    {
        if ($this->status !== OrderStatus::Ready) {
            throw new OrderConflictException('orders.transition');
        }

        if ($this->amounts->paid < $this->amounts->subtotal) {
            throw new OrderConflictException('orders.balance_due');
        }

        $this->status = OrderStatus::Delivered;
    }

    /** Return a line of this aggregate, concealing a line from another order. */
    public function line(string $id): OrderLine
    {
        foreach ($this->lines as $line) {
            if ($line->id === $id) {
                return $line;
            }
        }

        throw new OrderNotFoundException('orders.not_found');
    }

    /** Remove an unavailable bottle from the amounts; money remains paid until a refund succeeds. */
    public function markLineUnavailable(string $id, DateTimeImmutable $at, ?int $grossPaid = null): int
    {
        $line = $this->line($id);

        if ($this->status !== OrderStatus::Ordered || $line->kind !== OrderLineKind::Bottle || $line->status !== OrderLineStatus::Ordered) {
            throw new OrderConflictException('orders.line_transition');
        }

        $refund = $this->amountPaidFor($line, $grossPaid ?? $this->amounts->paid);
        $line->advanceTo(OrderLineStatus::Unavailable);
        $this->amounts = OrderAmounts::calculate($this->lines, $this->amounts->paid, $this->depositPercent);

        if (! array_any($this->lines, fn (OrderLine $item): bool => ! in_array($item->status, [OrderLineStatus::Unavailable, OrderLineStatus::Released], true))) {
            $this->status = OrderStatus::Cancelled;
            $this->cancelledAt = $at;
        }

        return $refund;
    }

    /** Allocate the deposit with cumulative rounding, so odd prices never over-refund a colon. */
    private function amountPaidFor(OrderLine $target, int $grossPaid): int
    {
        $bottlesBefore = 0;
        $decants = 0;
        $bottlesTotal = 0;

        foreach ($this->lines as $line) {
            if ($line->kind === OrderLineKind::Decant && $line->decantPackId === null) {
                $decants += $line->total();
            } elseif ($line->kind === OrderLineKind::Bottle) {
                $bottlesTotal += $line->total();
            }
        }

        $deposit = intdiv($bottlesTotal * $this->depositPercent + 99, 100);
        $bottlePaid = min($bottlesTotal, max(0, $grossPaid - $decants));
        $depositPaid = min($deposit, $bottlePaid);
        $extraPaid = max(0, $bottlePaid - $deposit);

        foreach ($this->lines as $line) {
            if ($line->kind !== OrderLineKind::Bottle) {
                continue;
            }

            $before = intdiv($bottlesBefore * $this->depositPercent + 99, 100);
            $after = intdiv(($bottlesBefore + $line->total()) * $this->depositPercent + 99, 100);

            if ($line === $target) {
                $depositPart = min($after, $depositPaid) - min($before, $depositPaid);
                $remainingBefore = $bottlesBefore - $before;
                $remainingAfter = $bottlesBefore + $line->total() - $after;

                return $depositPart + min($remainingAfter, $extraPaid) - min($remainingBefore, $extraPaid);
            }

            $bottlesBefore += $line->total();
        }

        return 0;
    }

    private function hasBottles(): bool
    {
        return array_any($this->lines, fn (OrderLine $line): bool => $line->kind === OrderLineKind::Bottle);
    }

    private function hasPreparedDecants(): bool
    {
        return array_any($this->lines, fn (OrderLine $line): bool => $line->status === OrderLineStatus::Prepared);
    }
}

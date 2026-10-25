<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Domain\Entities;

use Src\Sales\Orders\Domain\Exceptions\OrderConflictException;
use Src\Sales\Orders\Domain\ValueObjects\OrderLineKind;
use Src\Sales\Orders\Domain\ValueObjects\OrderLineStatus;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\Colones;

final class OrderLine
{
    /** Hold one frozen line, referencing other aggregates only by identity. */
    private function __construct(
        public readonly string $id,
        public readonly OrderLineKind $kind,
        public readonly ?string $perfumeVariantId,
        public readonly ?string $decantBottleId,
        public readonly ?int $ml,
        public readonly int $quantity,
        public readonly Colones $unitPrice,
        public private(set) OrderLineStatus $status,
        public readonly ?string $decantPackId = null,
        public readonly ?string $purchaseOrderLineId = null,
        public readonly ?string $perfumeName = null,
        public readonly ?int $variantMl = null,
    ) {}

    /** Freeze a presentation price or the price of one decant. */
    public static function create(string $id, OrderLineKind $kind, ?string $variantId, ?string $bottleId, ?int $ml, int $quantity, int $price): self
    {
        if ($quantity < 1 || ($kind === OrderLineKind::Bottle && ($variantId === null || $bottleId !== null || $ml !== null))
            || ($kind === OrderLineKind::Decant && ($bottleId === null || $ml === null || $ml < 1 || $quantity !== 1))) {
            throw new InvalidValueException('orders.invalid_line');
        }

        $line = new self($id, $kind, $variantId, $bottleId, $ml, $quantity, new Colones($price), $kind === OrderLineKind::Bottle ? OrderLineStatus::Pending : OrderLineStatus::Reserved);
        new Colones($line->total());

        return $line;
    }

    /** Rebuild a stored line without changing its frozen price. */
    public static function reconstitute(string $id, OrderLineKind $kind, ?string $variantId, ?string $bottleId, ?int $ml, int $quantity, int $price, OrderLineStatus $status, ?string $packId = null, ?string $purchaseLineId = null, ?string $perfumeName = null, ?int $variantMl = null): self
    {
        return new self($id, $kind, $variantId, $bottleId, $ml, $quantity, new Colones($price), $status, $packId, $purchaseLineId, $perfumeName, $variantMl);
    }

    /** Amount agreed when the order was placed. */
    public function total(): int
    {
        return $this->quantity * $this->unitPrice->amount;
    }

    /** Follow the lifecycle of the kind of line. */
    public function advanceTo(OrderLineStatus $target): void
    {
        $allowed = match ($this->status) {
            OrderLineStatus::Pending => [OrderLineStatus::Ordered],
            OrderLineStatus::Ordered => [OrderLineStatus::Received, OrderLineStatus::Unavailable],
            OrderLineStatus::Reserved => [OrderLineStatus::Prepared, OrderLineStatus::Released],
            default => [],
        };

        if (! in_array($target, $allowed, true)) {
            throw new OrderConflictException('orders.line_transition');
        }

        $this->status = $target;
    }
}

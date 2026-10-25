<?php

declare(strict_types=1);

use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Domain\Entities\OrderLine;
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

/** @param list<OrderLine> $lines */
function salesOrder(array $lines, int $depositPercent = 50): Order
{
    return Order::create(
        new OrderId('019a0010-0000-7000-8000-000000000001'),
        new BusinessId('019a0010-0000-7000-8000-000000000002'),
        '019a0010-0000-7000-8000-000000000003',
        $lines,
        new DateTimeImmutable('2026-10-24T10:00:00-06:00'),
        new DateTimeImmutable('2026-10-24T10:30:00-06:00'),
        $depositPercent,
    );
}

function salesBottle(int $price = 15000, string $id = 'line-1'): OrderLine
{
    return OrderLine::create($id, OrderLineKind::Bottle, 'variant-1', null, null, 1, $price);
}

function salesDecant(int $price = 1000): OrderLine
{
    return OrderLine::create('line-2', OrderLineKind::Decant, 'variant-1', 'bottle-1', 5, 1, $price);
}

it('R24: calculates the deposit once over all bottles and rounds an odd total up by one colon', function () {
    $order = salesOrder([salesBottle(15001), salesBottle(15001, 'line-3'), salesDecant(501)]);

    expect($order->amounts)->toEqual(new OrderAmounts(30503, 15502, 0, 15001));
    expect($order->status)->toBe(OrderStatus::PendingPayment);
});

it('calculates integer amounts for each kind of order', function (array $lines, int $subtotal, int $due, int $balance) {
    $order = salesOrder(array_values($lines));

    expect($order->amounts)->toEqual(new OrderAmounts($subtotal, $due, 0, $balance));
})->with([
    'bottles' => [[salesBottle()], 15000, 7500, 7500],
    'decants' => [[salesDecant()], 1000, 1000, 0],
    'mixed' => [[salesBottle(), salesDecant()], 16000, 8500, 7500],
    'free' => [[salesBottle(0), salesDecant(0)], 0, 0, 0],
]);

it('confirms an order with nothing to charge without a gateway', function () {
    expect(salesOrder([salesDecant(0)])->status)->toBe(OrderStatus::Confirmed);
});

it('collects the full bottle amount upfront when the configured deposit is 100 percent', function () {
    $order = salesOrder([salesBottle(15001)], 100);

    expect($order->amounts)->toEqual(new OrderAmounts(15001, 15001, 0, 0));
});

it('rejects an empty order', function () {
    expect(fn () => salesOrder([]))->toThrow(InvalidValueException::class, 'orders.empty');
});

it('keeps the agreed line price after preparing or changing order state', function () {
    $order = salesOrder([salesDecant(1200)]);
    $order->recordPayment(1200);
    $order->prepareDecants();

    expect($order->lines[0]->unitPrice->amount)->toBe(1200);
    expect($order->status)->toBe(OrderStatus::Ready);
    expect($order->amounts->paid)->toBe(1200);
});

it('confirms only when the initial payment is fully covered', function () {
    $order = salesOrder([salesBottle()]);
    $order->recordPayment(3000);
    expect($order->status)->toBe(OrderStatus::PendingPayment);

    $order->recordPayment(4500);
    expect($order->status)->toBe(OrderStatus::Confirmed);
    expect($order->amounts->balance)->toBe(7500);
});

it('does not accept negative, zero or excessive credits', function (int $amount) {
    $order = salesOrder([salesBottle()]);

    expect(fn () => $order->recordPayment($amount))->toThrow(OrderConflictException::class);
    expect($order->amounts->paid)->toBe(0);
})->with([-1, 0, 15001]);

it('records a late approval without reopening a cancelled or expired order or changing its released decants', function (bool $expired) {
    $order = salesOrder([salesBottle(), salesDecant()]);
    $at = new DateTimeImmutable('2026-10-25T10:10:00-06:00');

    if ($expired) {
        $order->expire($at);
    } else {
        $order->cancel($at);
    }

    $order->lines[1]->advanceTo(OrderLineStatus::Released);
    $status = $order->status;
    $cancelledAt = $order->cancelledAt;

    $order->recordLatePayment(8500);

    expect($order->amounts->paid)->toBe(8500);
    expect($order->status)->toBe($status);
    expect($order->cancelledAt)->toBe($cancelledAt);
    expect($order->lines[0]->status)->toBe(OrderLineStatus::Pending);
    expect($order->lines[1]->status)->toBe(OrderLineStatus::Released);
})->with([false, true]);

it('records a late approval even when unavailable lines reduced the order subtotal to zero', function () {
    $order = salesOrder([salesBottle()]);
    $order->recordPayment(7500);
    $order->markOrdered();
    $order->markLineUnavailable('line-1', new DateTimeImmutable('2026-10-25T10:10:00-06:00'), 7500);
    $order->recordRefund(7500);

    $order->recordLatePayment(7500);

    expect($order->status)->toBe(OrderStatus::Cancelled);
    expect($order->amounts->subtotal)->toBe(0);
    expect($order->amounts->paid)->toBe(7500);
    expect($order->lines[0]->status)->toBe(OrderLineStatus::Unavailable);
});

it('does not record late payments for an active or delivered order', function (OrderStatus $status) {
    $original = salesOrder([salesBottle()]);
    $order = Order::reconstitute($original->id, $original->businessId, $original->customerId, $original->lines, $status, $original->amounts, $original->createdAt, $original->expiresAt);

    expect(fn () => $order->recordLatePayment(7500))->toThrow(OrderConflictException::class);
    expect($order->amounts->paid)->toBe(0);
    expect($order->status)->toBe($status);
})->with([OrderStatus::PendingPayment, OrderStatus::Confirmed, OrderStatus::Ordered, OrderStatus::Ready, OrderStatus::Delivered]);

it('rejects nonpositive or overflowing late credits without losing the previous paid amount', function (int $paid, int $amount) {
    $original = salesOrder([salesBottle()]);
    $order = Order::reconstitute($original->id, $original->businessId, $original->customerId, $original->lines, OrderStatus::Cancelled,
        new OrderAmounts(15000, 7500, $paid, 7500), $original->createdAt, $original->expiresAt);

    expect(fn () => $order->recordLatePayment($amount))->toThrow(OrderConflictException::class);
    expect($order->amounts->paid)->toBe($paid);
})->with([[0, -1], [0, 0], [0, Colones::MAX + 1], [0, PHP_INT_MAX], [Colones::MAX, 1]]);

it('accepts a late credit exactly at the PostgreSQL integer limit', function () {
    $order = salesOrder([salesBottle()]);
    $order->cancel(new DateTimeImmutable('2026-10-25T10:10:00-06:00'));

    $order->recordLatePayment(Colones::MAX);

    expect($order->amounts->paid)->toBe(Colones::MAX);
    expect($order->status)->toBe(OrderStatus::Cancelled);
});

it('deducts only completed refunds while keeping the cancelled order state', function () {
    $order = salesOrder([salesBottle()]);
    $order->recordPayment(7500);
    $order->cancel(new DateTimeImmutable('2026-10-24T10:10:00-06:00'));
    $order->recordRefund(7500);

    expect($order->status)->toBe(OrderStatus::Cancelled);
    expect($order->amounts->paid)->toBe(0);
    expect(fn () => $order->recordRefund(1))->toThrow(OrderConflictException::class);
});

it('rejects negative, zero or excessive refund amounts', function (int $amount) {
    $order = salesOrder([salesBottle()]);
    $order->recordPayment(7500);

    expect(fn () => $order->recordRefund($amount))->toThrow(OrderConflictException::class);
    expect($order->amounts->paid)->toBe(7500);
})->with([-1, 0, 7501]);

it('prepares a paid mixed order without skipping the supplier workflow', function () {
    $order = salesOrder([salesBottle(), salesDecant()]);
    $order->recordPayment(8500);
    $order->prepareDecants();

    expect($order->status)->toBe(OrderStatus::Confirmed);
    expect($order->lines[1]->status)->toBe(OrderLineStatus::Prepared);

    $order->markOrdered();
    $order->lines[0]->advanceTo(OrderLineStatus::Received);
    $order->markReady();
    expect($order->status)->toBe(OrderStatus::Ready);

    expect(fn () => $order->deliver())->toThrow(OrderConflictException::class, 'orders.balance_due');

    $order->recordPayment(7500);
    $order->deliver();
    expect($order->status)->toBe(OrderStatus::Delivered);
});

it('does not prepare an unpaid order or prepare the same decant twice', function () {
    $order = salesOrder([salesDecant()]);
    expect(fn () => $order->prepareDecants())->toThrow(OrderConflictException::class);

    $order->recordPayment(1000);
    $order->prepareDecants();
    expect(fn () => $order->prepareDecants())->toThrow(OrderConflictException::class);
});

it('does not mark an ordered mixed order ready until every line is fulfilled', function () {
    $order = salesOrder([salesBottle(), salesDecant()]);
    $order->recordPayment(8500);
    $order->markOrdered();
    $order->lines[0]->advanceTo(OrderLineStatus::Received);

    expect(fn () => $order->markReady())->toThrow(OrderConflictException::class, 'orders.not_ready');
    $order->prepareDecants();
    expect($order->status)->toBe(OrderStatus::Ordered);
    $order->markReady();
    expect($order->status)->toBe(OrderStatus::Ready);
});

it('cancels a pending or confirmed order with the exact cancellation time', function (bool $paid) {
    $order = salesOrder([salesBottle()]);
    if ($paid) {
        $order->recordPayment(7500);
    }
    $at = new DateTimeImmutable('2026-10-24T10:10:00-06:00');

    $order->cancel($at);

    expect($order->status)->toBe(OrderStatus::Cancelled);
    expect($order->cancelledAt)->toBe($at);
})->with([false, true]);

it('does not cancel an ordered order or an order whose decant was prepared', function () {
    $order = salesOrder([salesBottle(), salesDecant()]);
    $order->recordPayment(8500);
    $order->prepareDecants();

    expect(fn () => $order->cancel(new DateTimeImmutable))->toThrow(OrderConflictException::class);
    $order->markOrdered();
    expect(fn () => $order->cancel(new DateTimeImmutable))->toThrow(OrderConflictException::class);
});

it('expires a pending order only when its deadline arrives', function () {
    $order = salesOrder([salesBottle()]);

    expect(fn () => $order->expire(new DateTimeImmutable('2026-10-24T10:29:59-06:00')))->toThrow(OrderConflictException::class);
    $order->expire(new DateTimeImmutable('2026-10-24T10:30:00-06:00'));

    expect($order->status)->toBe(OrderStatus::Expired);
    expect(fn () => $order->recordPayment(7500))->toThrow(OrderConflictException::class);
});

it('removes an unavailable bottle and returns its allocated deposit without erasing actual paid money', function () {
    $order = salesOrder([salesBottle(15001), salesBottle(15001, 'line-3'), salesDecant(501)]);
    $order->recordPayment(15502);
    $order->markOrdered();

    expect($order->markLineUnavailable('line-1', new DateTimeImmutable('2026-10-24T10:15:00-06:00')))->toBe(7501);
    expect($order->amounts)->toEqual(new OrderAmounts(15502, 8002, 15502, 0));
    expect($order->markLineUnavailable('line-3', new DateTimeImmutable('2026-10-24T10:15:00-06:00')))->toBe(7500);
    expect($order->amounts->paid)->toBe(15502);
});

it('cancels the order when its last available line is unavailable and returns everything paid for that line', function (int $paid) {
    $order = salesOrder([salesBottle()]);
    $order->recordPayment($paid);
    $order->markOrdered();
    $at = new DateTimeImmutable('2026-10-25T10:10:00-06:00');

    expect($order->markLineUnavailable('line-1', $at, $paid))->toBe($paid);
    expect($order->status)->toBe(OrderStatus::Cancelled);
    expect($order->cancelledAt)->toBe($at);
    expect($order->amounts->subtotal)->toBe(0);
    expect($order->amounts->paid)->toBe($paid);
})->with([7500, 15000]);

it('keeps the original allocation after an earlier line refund succeeds', function () {
    $order = salesOrder([salesBottle(15001), salesBottle(15001, 'line-3')]);
    $order->recordPayment(15001);
    $order->markOrdered();
    $at = new DateTimeImmutable('2026-10-25T10:10:00-06:00');

    expect($order->markLineUnavailable('line-1', $at, 15001))->toBe(7501);
    $order->recordRefund(7501);
    expect($order->markLineUnavailable('line-3', $at, 15001))->toBe(7500);
    expect($order->status)->toBe(OrderStatus::Cancelled);
    expect($order->amounts->paid)->toBe(7500);
});

it('conceals a line from another order and rejects invalid unavailability transitions', function () {
    $order = salesOrder([salesBottle(), salesDecant()]);

    expect(fn () => $order->markLineUnavailable('missing', new DateTimeImmutable('2026-10-24T10:15:00-06:00')))->toThrow(OrderNotFoundException::class);
    expect(fn () => $order->markLineUnavailable('line-1', new DateTimeImmutable('2026-10-24T10:15:00-06:00')))->toThrow(OrderConflictException::class);
});

it('allows only the specified transition for each line', function (OrderLineKind $kind, OrderLineStatus $origin, OrderLineStatus $target, bool $allowed) {
    $line = OrderLine::reconstitute('line', $kind, 'variant', $kind === OrderLineKind::Decant ? 'bottle' : null, $kind === OrderLineKind::Decant ? 5 : null, 1, 1000, $origin);

    if (! $allowed) {
        expect(fn () => $line->advanceTo($target))->toThrow(OrderConflictException::class);
        expect($line->status)->toBe($origin);

        return;
    }

    $line->advanceTo($target);
    expect($line->status)->toBe($target);
})->with([
    [OrderLineKind::Bottle, OrderLineStatus::Pending, OrderLineStatus::Ordered, true],
    [OrderLineKind::Bottle, OrderLineStatus::Ordered, OrderLineStatus::Received, true],
    [OrderLineKind::Bottle, OrderLineStatus::Ordered, OrderLineStatus::Unavailable, true],
    [OrderLineKind::Bottle, OrderLineStatus::Pending, OrderLineStatus::Received, false],
    [OrderLineKind::Bottle, OrderLineStatus::Received, OrderLineStatus::Unavailable, false],
    [OrderLineKind::Decant, OrderLineStatus::Reserved, OrderLineStatus::Prepared, true],
    [OrderLineKind::Decant, OrderLineStatus::Reserved, OrderLineStatus::Released, true],
    [OrderLineKind::Decant, OrderLineStatus::Prepared, OrderLineStatus::Released, false],
]);

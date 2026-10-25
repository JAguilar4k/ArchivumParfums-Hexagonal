<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\Contracts;

interface RefundRequests
{
    /** Request a durable refund of confirmed payments, without a provider call in this transaction. */
    public function request(string $orderId, int $amount, ?string $lineId = null): void;

    /** Principal ever successfully collected, including amounts subsequently refunded. */
    public function grossPaid(string $orderId): int;

    /** Collected money that is neither refunded nor reserved by a pending refund. */
    public function remaining(string $orderId): int;

    /** A provider operation must finish before the order can be delivered or charged again. */
    public function hasPendingRefunds(string $orderId): bool;
}

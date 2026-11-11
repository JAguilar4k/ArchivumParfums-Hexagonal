<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Infrastructure\Jobs;

use Src\Sales\Orders\Application\DTOs\ExpireUnpaidOrderCommand;
use Src\Sales\Orders\Application\UseCases\ExpireUnpaidOrder;
use Src\Shared\Infrastructure\Queue\IdempotentJob;
use Src\Shared\Infrastructure\Queue\ScopeJobToBusiness;

/** Runs when the deadline of an order arrives and expires it if nobody paid it in time (#158). */
final class ExpireUnpaidOrderJob extends IdempotentJob
{
    /** Carry the order and its business. */
    public function __construct(
        public readonly string $orderId,
        public readonly string $businessId,
    ) {}

    /** One copy per order waits in the queue. */
    public function uniqueId(): string
    {
        return $this->orderId;
    }

    /**
     * Run in one transaction of the business of the order: a worker has no request to take the business from.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new ScopeJobToBusiness($this->businessId)];
    }

    /** Expire the order if it is still unpaid; the use case is resolved after the middleware binds the business. */
    public function handle(ExpireUnpaidOrder $expireUnpaidOrder): void
    {
        $expireUnpaidOrder->execute(new ExpireUnpaidOrderCommand($this->orderId, now()->toIso8601String()));
    }
}

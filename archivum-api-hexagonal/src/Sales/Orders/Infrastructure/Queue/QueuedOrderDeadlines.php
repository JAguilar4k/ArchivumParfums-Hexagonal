<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Infrastructure\Queue;

use DateTimeImmutable;
use Src\Sales\Orders\Application\Contracts\OrderDeadlines;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;
use Src\Sales\Orders\Infrastructure\Jobs\ExpireUnpaidOrderJob;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class QueuedOrderDeadlines implements OrderDeadlines
{
    /**
     * Leave in the queue a job that only becomes available at the deadline. The queue writes it once the transaction
     * that placed the order commits, so an order that rolled back leaves no job (#156).
     */
    public function checkAt(OrderId $orderId, BusinessId $businessId, DateTimeImmutable $moment): void
    {
        ExpireUnpaidOrderJob::dispatch($orderId->value, $businessId->value)->delay($moment);
    }
}

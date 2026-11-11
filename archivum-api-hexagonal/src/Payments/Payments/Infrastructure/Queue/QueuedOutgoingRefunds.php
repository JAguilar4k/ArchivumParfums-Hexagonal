<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Queue;

use Src\Payments\Payments\Application\Contracts\OutgoingRefunds;
use Src\Payments\Payments\Infrastructure\Jobs\RequestRefundJob;

final readonly class QueuedOutgoingRefunds implements OutgoingRefunds
{
    /**
     * Leave the refund in the queue; the operation that registered it answers without waiting for the provider. The
     * queue keeps its jobs in the database, so the job is written in the transaction that registered the refund and
     * exists only if the refund does.
     */
    public function send(string $refundId, string $businessId): void
    {
        RequestRefundJob::dispatch($refundId, $businessId);
    }
}

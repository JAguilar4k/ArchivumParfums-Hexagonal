<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Queue;

use Src\Payments\Payments\Application\Contracts\OutgoingRefunds;
use Src\Payments\Payments\Infrastructure\Jobs\RequestRefundJob;

final readonly class QueuedOutgoingRefunds implements OutgoingRefunds
{
    /**
     * Leave the refund in the queue; the operation that registered it answers without waiting for the provider. The
     * queue writes the job once the transaction that registered the refund commits, so a refund that rolled back
     * leaves no job, and a refund whose job is still waiting gets no second one (#156).
     */
    public function send(string $refundId, string $businessId): void
    {
        RequestRefundJob::dispatch($refundId, $businessId);
    }
}

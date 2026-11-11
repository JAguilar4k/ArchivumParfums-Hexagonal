<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Queue;

use Src\Payments\Payments\Application\Contracts\PendingPaymentEvents;
use Src\Payments\Payments\Infrastructure\Jobs\ProcessPaymentEventJob;

final readonly class QueuedPendingPaymentEvents implements PendingPaymentEvents
{
    /**
     * Leave the notice in the queue; the webhook answers without waiting for the provider. The queue keeps its jobs in
     * the database, so the job is written in the transaction that kept the notice and exists only if the notice does.
     */
    public function process(string $id): void
    {
        ProcessPaymentEventJob::dispatch($id);
    }
}

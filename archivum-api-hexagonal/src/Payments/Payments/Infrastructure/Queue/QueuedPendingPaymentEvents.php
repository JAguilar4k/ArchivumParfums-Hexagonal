<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Queue;

use Src\Payments\Payments\Application\Contracts\PendingPaymentEvents;
use Src\Payments\Payments\Infrastructure\Jobs\ProcessPaymentEventJob;

final readonly class QueuedPendingPaymentEvents implements PendingPaymentEvents
{
    /**
     * Leave the notice in the queue; the webhook answers without waiting for the provider. The queue writes the job
     * once the transaction that kept the notice commits, so a notice that rolled back leaves no job (#156).
     */
    public function process(string $id): void
    {
        ProcessPaymentEventJob::dispatch($id);
    }
}

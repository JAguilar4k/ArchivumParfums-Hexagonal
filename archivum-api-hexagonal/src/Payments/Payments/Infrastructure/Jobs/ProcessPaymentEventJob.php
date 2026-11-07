<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Src\Payments\Payments\Application\UseCases\ProcessPaymentEvent;
use Src\Payments\Payments\Infrastructure\Tenancy\PaymentEventBusinessContext;
use Src\Shared\Domain\Contracts\BusinessContext;

/** Processes a verified notice of a payment gateway that the webhook kept, apart from the request that brought it. */
final class ProcessPaymentEventJob implements ShouldQueue
{
    use Queueable;

    /** Attempts before the job is recorded as failed. */
    public int $tries = 5;

    /** @var list<int> seconds to wait before the second, third and later attempts */
    public array $backoff = [10, 60, 300];

    /** Carry the identifier of the kept notice in webhook_events. */
    public function __construct(public readonly string $webhookEventId) {}

    /**
     * A worker has no request that names a business: the use case is resolved only after the context of the notice is
     * bound, so every repository it uses asks the business that ProcessPaymentEvent pins for the payment. The binding
     * is removed afterwards, because a worker keeps its container for the next job.
     */
    public function handle(): void
    {
        app()->instance(BusinessContext::class, app(PaymentEventBusinessContext::class));

        try {
            app(ProcessPaymentEvent::class)->execute($this->webhookEventId);
        } finally {
            app()->forgetInstance(BusinessContext::class);
        }
    }
}

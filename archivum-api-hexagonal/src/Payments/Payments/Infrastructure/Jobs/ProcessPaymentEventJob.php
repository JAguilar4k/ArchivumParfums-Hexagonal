<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Jobs;

use Src\Payments\Payments\Application\UseCases\ProcessPaymentEvent;
use Src\Payments\Payments\Infrastructure\Tenancy\PaymentEventBusinessContext;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Infrastructure\Queue\IdempotentJob;

/**
 * Processes a verified notice of a payment gateway that the webhook kept, apart from the request that brought it. It is
 * the only job that carries no business, so it does not go through ScopeJobToBusiness: the notice arrives without a
 * session, and the business comes from the payment it names (#95).
 */
final class ProcessPaymentEventJob extends IdempotentJob
{
    /** Carry the identifier of the kept notice in webhook_events. */
    public function __construct(public readonly string $webhookEventId) {}

    /** One copy per kept notice waits in the queue. */
    public function uniqueId(): string
    {
        return $this->webhookEventId;
    }

    /**
     * The use case is resolved only after the context of the notice is bound, so every repository it uses asks the
     * business that ProcessPaymentEvent pins for the payment. The binding is removed afterwards, because a worker keeps
     * its container for the next job.
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

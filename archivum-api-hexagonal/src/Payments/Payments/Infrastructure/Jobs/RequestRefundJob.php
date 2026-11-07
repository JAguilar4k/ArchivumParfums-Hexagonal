<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Src\Payments\Payments\Application\DTOs\RequestRefundCommand;
use Src\Payments\Payments\Application\UseCases\RequestRefund;
use Src\Payments\Payments\Infrastructure\Tenancy\PaymentEventBusinessContext;
use Src\Shared\Domain\Contracts\BusinessContext;

/** Sends a registered refund to the provider of its payment, apart from the operation that registered it (#97). */
final class RequestRefundJob implements ShouldQueue
{
    use Queueable;

    /** Attempts before the job is recorded as failed. */
    public int $tries = 5;

    /** @var list<int> seconds to wait before the second, third and later attempts */
    public array $backoff = [10, 60, 300];

    /** Carry the refund to send and its business. */
    public function __construct(
        public readonly string $refundId,
        public readonly string $businessId,
    ) {}

    /**
     * A worker has no request that names a business: the use case is resolved only after this context is bound, so
     * every repository it uses asks the business that RequestRefund pins for the refund. The binding is removed
     * afterwards, because a worker keeps its container for the next job.
     */
    public function handle(): void
    {
        app()->instance(BusinessContext::class, app(PaymentEventBusinessContext::class));

        try {
            app(RequestRefund::class)->execute(new RequestRefundCommand($this->refundId, $this->businessId));
        } finally {
            app()->forgetInstance(BusinessContext::class);
        }
    }
}

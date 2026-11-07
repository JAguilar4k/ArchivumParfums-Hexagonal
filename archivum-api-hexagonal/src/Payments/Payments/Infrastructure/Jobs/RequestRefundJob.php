<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Jobs;

use Src\Payments\Payments\Application\DTOs\RequestRefundCommand;
use Src\Payments\Payments\Application\UseCases\RequestRefund;
use Src\Shared\Infrastructure\Queue\IdempotentJob;
use Src\Shared\Infrastructure\Queue\ScopeJobToBusiness;

/** Sends a registered refund to the provider of its payment, apart from the operation that registered it (#97). */
final class RequestRefundJob extends IdempotentJob
{
    /** Carry the refund to send and its business. */
    public function __construct(
        public readonly string $refundId,
        public readonly string $businessId,
    ) {}

    /** One copy per refund waits in the queue. */
    public function uniqueId(): string
    {
        return $this->refundId;
    }

    /**
     * Run as the business of the refund. RequestRefund opens its own transactions, because it commits its attempt
     * before it calls the provider.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new ScopeJobToBusiness($this->businessId, ScopeJobToBusiness::CONTEXT)];
    }

    /** Send the refund; the use case is resolved after the middleware binds the business of the job. */
    public function handle(RequestRefund $requestRefund): void
    {
        $requestRefund->execute(new RequestRefundCommand($this->refundId));
    }
}

<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Queue;

use Closure;
use LogicException;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

/**
 * Middleware of a job of one business, the counterpart of ScopeRequestToBusiness for the queue (#156). A worker has no
 * request that names a business, so each job carries the identifier of its own.
 */
final readonly class ScopeJobToBusiness
{
    public const string ATOMIC = 'atomic';

    public const string CONTEXT = 'context';

    /** Receive the business the job carries and whether the whole job runs in one transaction of that business. */
    public function __construct(
        private ?string $businessId,
        private string $transactionMode = self::ATOMIC,
    ) {}

    /**
     * Bind the business of the job as the BusinessContext before the job runs, and run the job in one transaction
     * pinned to that business (app.business_id). In context mode the use case opens its own transactions instead, each
     * pinned to the same business, as the payment routes do: a job that calls a provider commits its attempt first.
     * The previous context comes back afterwards, because a worker keeps its container for the next job.
     *
     * A job without a valid business fails at once, without running and without new attempts: no repository would know
     * whose rows to reach, so nothing is written.
     */
    public function handle(IdempotentJob $job, Closure $next): void
    {
        try {
            $business = new BusinessId((string) $this->businessId);
        } catch (InvalidValueException) {
            $job->fail(new LogicException('The job carries no business.'));

            return;
        }

        $previous = app(BusinessContext::class);
        app()->instance(BusinessContext::class, new JobBusinessContext($business));

        try {
            if ($this->transactionMode === self::CONTEXT) {
                $next($job);
            } else {
                app(BusinessTransaction::class)->run($business->value, fn (): mixed => $next($job));
            }
        } finally {
            app()->instance(BusinessContext::class, $previous);
        }
    }
}

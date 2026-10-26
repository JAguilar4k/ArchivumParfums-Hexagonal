<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Tenancy;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Src\Shared\Domain\Contracts\BusinessContext;
use Symfony\Component\HttpFoundation\Response;

final readonly class ScopeRequestToBusiness
{
    public const string STAFF = 'staff';

    public const string ROUTE = 'route';

    public const string ACCOUNT = 'account';

    /** Receive the container that holds each adapter of BusinessContext and the transaction of one business. */
    public function __construct(
        private Container $container,
        private BusinessTransaction $transaction,
    ) {}

    /** Name under which a module binds the adapter of BusinessContext for one group of routes. */
    public static function contextFor(string $source): string
    {
        return "business-context.{$source}";
    }

    /**
     * Resolve the business with the adapter of the route group, before any use case runs,
     * and run the request in one transaction pinned to that business.
     */
    public function handle(Request $request, Closure $next, string $source, string $transactionMode = 'atomic'): Response
    {
        $context = $this->container->make(self::contextFor($source));
        assert($context instanceof BusinessContext);

        $this->container->instance(BusinessContext::class, $context);
        $businessId = $context->current();

        if ($transactionMode === 'context') {
            // Payment use cases commit their intent before making a non-idempotent external request.
            return $next($request);
        }

        return $this->transaction->run($businessId->value, fn (): Response => $next($request));
    }
}

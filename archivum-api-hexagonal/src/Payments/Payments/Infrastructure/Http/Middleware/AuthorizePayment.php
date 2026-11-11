<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ask PaymentPolicy about the {pago} of the route before the controller runs. The payment routes keep the business
 * as context only, so the lookup opens its own transaction pinned to that business for the row-level policies.
 */
final readonly class AuthorizePayment
{
    /** Receive the gate, the payments, the business of the request and its transaction. */
    public function __construct(
        private Gate $gate,
        private PaymentRepository $payments,
        private BusinessContext $business,
        private BusinessTransaction $transaction,
    ) {}

    /**
     * Load the payment under the business of the request and authorize the ability of PaymentPolicy given.
     *
     * @throws AuthorizationException
     * @throws PaymentNotFoundException
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $this->transaction->run($this->business->current()->value, function () use ($request, $ability): void {
            $payment = $this->payments->find(new PaymentId((string) $request->route('pago')))
                ?? throw new PaymentNotFoundException('payments.not_found');

            $this->gate->forUser($request->user())->authorize($ability, $payment);
        });

        return $next($request);
    }
}

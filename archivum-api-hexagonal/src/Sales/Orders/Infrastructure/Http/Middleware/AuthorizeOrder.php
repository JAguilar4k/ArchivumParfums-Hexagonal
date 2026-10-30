<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use Src\Sales\Orders\Domain\Contracts\OrderRepository;
use Src\Sales\Orders\Domain\Exceptions\OrderNotFoundException;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ask OrderPolicy about the {pedido} of the route before the controller runs, so a refused request writes nothing.
 * It goes after the business middleware, which resolves the business of the request.
 */
final readonly class AuthorizeOrder
{
    /** Receive the gate, the orders, the business of the request and its transaction. */
    public function __construct(
        private Gate $gate,
        private OrderRepository $orders,
        private BusinessContext $business,
        private BusinessTransaction $transaction,
    ) {}

    /**
     * Load the order under the business of the request and authorize the ability of OrderPolicy given.
     *
     * @throws AuthorizationException
     * @throws OrderNotFoundException
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $this->transaction->run($this->business->current()->value, function () use ($request, $ability): void {
            $order = $this->orders->find(new OrderId((string) $request->route('pedido')))
                ?? throw new OrderNotFoundException('orders.not_found');

            $this->gate->forUser($request->user())->authorize($ability, $order);
        });

        return $next($request);
    }
}

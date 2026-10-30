<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Infrastructure\Policies;

use Illuminate\Auth\Access\Response;
use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Sales\Orders\Application\Contracts\OrderCustomers;
use Src\Sales\Orders\Domain\Entities\Order;
use Src\Shared\Domain\Contracts\BusinessContext;

/**
 * Who may reach an order, by role and by ownership (#104). The abilities of the token decide the role (#102); this
 * policy decides whose order it is. Any refusal looks like a missing order, so it never reveals that one exists.
 */
final readonly class OrderPolicy
{
    /** Receive the business of the request and the customers port. */
    public function __construct(
        private BusinessContext $business,
        private OrderCustomers $customers,
    ) {}

    /** The owner sees every order of their business; a customer, only their own. */
    public function view(UserModel $user, Order $order): Response
    {
        return $this->managedByOwner($user, $order) || $this->ownedByCustomer($user, $order)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /** Only the customer who placed the order cancels it from their account. */
    public function cancel(UserModel $user, Order $order): Response
    {
        return $this->ownedByCustomer($user, $order) ? Response::allow() : Response::denyAsNotFound();
    }

    /** Only the customer who placed the order pays for it. */
    public function pay(UserModel $user, Order $order): Response
    {
        return $this->ownedByCustomer($user, $order) ? Response::allow() : Response::denyAsNotFound();
    }

    /** Only the owner of the business prepares, delivers or refunds its orders. */
    public function manage(UserModel $user, Order $order): Response
    {
        return $this->managedByOwner($user, $order) ? Response::allow() : Response::denyAsNotFound();
    }

    /** An owner token on an order of the business of the session. */
    private function managedByOwner(UserModel $user, Order $order): bool
    {
        return $user->tokenCan(Ability::OrdersManage->value)
            && $order->businessId->value === $this->business->current()->value;
    }

    /** A customer token on an order whose customer row in its business is this account. */
    private function ownedByCustomer(UserModel $user, Order $order): bool
    {
        return $user->tokenCan(Ability::CustomerOwn->value)
            && $order->businessId->value === $this->business->current()->value
            && $this->customers->owns($order->customerId, $user->id);
    }
}

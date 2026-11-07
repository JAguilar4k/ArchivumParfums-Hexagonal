<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Policies;

use Illuminate\Auth\Access\Response;
use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Sales\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Shared\Domain\Contracts\BusinessContext;

/**
 * Who may reach a payment, by role and by ownership (#104): the customer whose order it pays, or the owner of the
 * business who records payments. Any refusal looks like a missing payment.
 */
final readonly class PaymentPolicy
{
    /** Receive the business of the request. */
    public function __construct(private BusinessContext $business) {}

    /** The customer who owns the paid order, or the owner of its business. */
    public function view(UserModel $user, Payment $payment): Response
    {
        if ($payment->businessId->value !== $this->business->current()->value) {
            return Response::denyAsNotFound();
        }

        $allowed = $user->tokenCan(Ability::PaymentsRecord->value)
            || ($user->tokenCan(Ability::CustomerOwn->value) && $this->paysAnOrderOf($user, $payment));

        return $allowed ? Response::allow() : Response::denyAsNotFound();
    }

    /** Whether the order the payment pays belongs to a customer row of this account in the business. */
    private function paysAnOrderOf(UserModel $user, Payment $payment): bool
    {
        return OrderModel::query()
            ->whereKey($payment->payable->id)
            ->where('business_id', $payment->businessId->value)
            ->whereIn('customer_id', CustomerModel::query()
                ->select('id')
                ->where('business_id', $payment->businessId->value)
                ->where('user_id', $user->id))
            ->exists();
    }
}

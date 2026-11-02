<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Sales;

use Illuminate\Support\Facades\DB;
use Src\Payments\Payments\Application\DTOs\RefundPaymentCommand;
use Src\Payments\Payments\Application\UseCases\RefundPayment;
use Src\Payments\Payments\Domain\Exceptions\InvalidPaymentTransitionException;
use Src\Sales\Orders\Application\Contracts\RefundRequests;
use Src\Shared\Domain\Contracts\BusinessContext;

final readonly class PaymentRefundRequests implements RefundRequests
{
    public function __construct(private BusinessContext $business, private RefundPayment $refundPayment) {}

    /**
     * Split a refund of an order among its confirmed payments, oldest first, and register each part with RefundPayment,
     * tied to its payment, which leaves the request to the provider in the queue (#97): nothing leaves for the provider
     * in the transaction of the cancellation or the unavailable line.
     */
    public function request(string $orderId, int $amount, ?string $lineId = null): void
    {
        $business = $this->business->current()->value;
        $remaining = $amount;
        $payments = DB::table('payments')->where('business_id', $business)->where('order_id', $orderId)
            ->whereIn('status', ['succeeded', 'partially_refunded'])->orderBy('id')->lockForUpdate()->get();

        foreach ($payments as $payment) {
            $reserved = (int) DB::table('refunds')->where('business_id', $business)->where('payment_id', $payment->id)->whereIn('status', ['pending', 'succeeded'])->sum('amount');
            $part = min($remaining, max(0, (int) $payment->amount - $reserved));
            if ($part === 0) {
                continue;
            }
            $this->refundPayment->execute(new RefundPaymentCommand((string) $payment->id, $part, $lineId));
            $remaining -= $part;
            if ($remaining === 0) {
                return;
            }
        }

        if ($remaining !== 0) {
            throw new InvalidPaymentTransitionException('payments.invalid_refund');
        }
    }

    /** Original confirmed principal remains available for per-line allocations after earlier refunds. */
    public function grossPaid(string $orderId): int
    {
        return (int) DB::table('payments')->where('business_id', $this->business->current()->value)
            ->where('order_id', $orderId)->whereIn('status', ['succeeded', 'partially_refunded', 'refunded'])->sum('amount');
    }

    /** Amount not yet allocated to a pending or completed refund. */
    public function remaining(string $orderId): int
    {
        $business = $this->business->current()->value;
        $allocated = (int) DB::table('refunds as r')->join('payments as p', fn ($join) => $join->on('p.id', '=', 'r.payment_id')->on('p.business_id', '=', 'r.business_id'))
            ->where('r.business_id', $business)->where('p.order_id', $orderId)->whereIn('r.status', ['pending', 'succeeded'])->sum('r.amount');

        return max(0, $this->grossPaid($orderId) - $allocated);
    }

    public function hasPendingRefunds(string $orderId): bool
    {
        return DB::table('refunds as r')->join('payments as p', fn ($join) => $join->on('p.id', '=', 'r.payment_id')->on('p.business_id', '=', 'r.business_id'))
            ->where('r.business_id', $this->business->current()->value)->where('p.order_id', $orderId)->whereIn('r.status', ['pending', 'failed'])->exists();
    }
}

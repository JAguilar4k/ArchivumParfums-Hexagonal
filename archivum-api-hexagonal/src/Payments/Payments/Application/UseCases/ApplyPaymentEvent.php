<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\UseCases;

use Src\Payments\Payments\Application\Services\PaymentSettlement;
use Src\Payments\Payments\Application\Services\RefundSettlement;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Contracts\RefundRepository;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Domain\Entities\Refund;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Domain\Exceptions\PaymentEventMismatchException;
use Src\Payments\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Payments\Payments\Domain\ValueObjects\PaymentEvent;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Payments\Payments\Domain\ValueObjects\RefundId;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class ApplyPaymentEvent
{
    /** Receive the ports of the payment and its refunds, the settlements that lock what they change and the transaction of the payment's business. */
    public function __construct(
        private PaymentRepository $payments,
        private RefundRepository $refunds,
        private PaymentSettlement $settlement,
        private RefundSettlement $refundSettlement,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Apply a verified notice to the payment it names, in one transaction of the business pinned for it. The notice
     * must name this payment's charge with its currency and exact amount, which only a notice of a failure may leave
     * out; then, while the payment is pending, it changes with what the provider answers to the server's own query,
     * together with its order or its decant pack. A payment that the expiry of its cancelled or expired order closed
     * changes only if the provider answers that the charge succeeded, which registers its refund (#192). A notice about
     * any other payment that is no longer pending changes nothing. A notice about a refund (#97) is applied to that
     * refund the same way.
     *
     * @throws PaymentNotFoundException when no payment of the business has the notice's references
     * @throws PaymentEventMismatchException when the notice names another charge, amount or currency
     * @throws GatewayUnavailableException when the provider cannot answer; nothing changes
     * @throws InvalidValueException when the provider answers an amount that is not the payment's
     */
    public function execute(PaymentEvent $event): void
    {
        if ($event->aboutRefund()) {
            $this->transaction->run(fn () => $this->applyToRefund($event));

            return;
        }

        $reference = $event->reference ?? throw new PaymentNotFoundException('payments.not_found');

        $this->transaction->run(function () use ($event, $reference): void {
            $payment = $this->paymentOf($event, $reference);
            // Lock in the same order as StartPayment and ReconcilePayment: the order or the pack first, then its payment.
            $payable = $this->settlement->lockPayable($payment->payable);
            $payment = $this->payments->find($payment->id, true) ?? throw new PaymentNotFoundException('payments.not_found');

            if ($payment->gateway !== $event->gateway || ! $payment->hasReference($reference)) {
                throw new PaymentEventMismatchException('payments.event_reference_mismatch');
            }

            if (! $payment->matchesCharge($event->currency, $event->amount, $event->reportsFailure)) {
                throw new PaymentEventMismatchException('payments.event_amount_mismatch');
            }

            if ($payment->status === PaymentStatus::Pending) {
                $this->settlement->settle($payment, $payable);
            } elseif ($payment->status === PaymentStatus::Expired) {
                // The expiry of its order closed the payment, but the provider may still have charged it (#192).
                $this->settlement->settleLate($payment, $payable);
            }
        });
    }

    /**
     * A notice about a refund names the refund Archivum sent, or the provider's reference of it, and the charge it
     * returns, with the refund's amount. While the refund is pending, it changes with what the provider answers to the
     * server's own query, together with its payment and its order. A notice that arrives before the job kept the
     * provider's answer brings that reference itself: its origin is verified and it names this refund.
     */
    private function applyToRefund(PaymentEvent $event): void
    {
        $refundReference = (string) $event->refundReference;
        $found = $this->refundOf($event, $refundReference);
        [$refund, $payment] = $this->refundSettlement->lock(new RefundId($found->id)) ?? throw new PaymentNotFoundException('payments.not_found');

        if ($payment->gateway !== $event->gateway
            || ($event->reference !== null && ! $payment->hasReference($event->reference))
            || ($refund->providerReference !== null && $refund->providerReference !== $refundReference)) {
            throw new PaymentEventMismatchException('payments.event_reference_mismatch');
        }

        if ($event->amount !== null && $refund->chargedAmount !== null && $event->amount !== $refund->chargedAmount) {
            throw new PaymentEventMismatchException('payments.event_amount_mismatch');
        }

        if ($refund->status !== 'pending') {
            return;
        }

        if ($refund->providerReference === null) {
            $refund->record($refundReference, 'pending');
            $this->refunds->save($refund);
        }

        $this->refundSettlement->settle($refund, $payment);
    }

    /** The refund named by the identifier Archivum sent with it or, without it, by the provider's reference. */
    private function refundOf(PaymentEvent $event, string $refundReference): Refund
    {
        $refund = $event->refundId !== null
            ? $this->refunds->find($event->refundId)
            : $this->refunds->findByReference($event->gateway, $refundReference);

        return $refund ?? throw new PaymentNotFoundException('payments.not_found');
    }

    /** The payment named by the identifier Archivum sent with the charge or, without it, by the provider's reference. */
    private function paymentOf(PaymentEvent $event, string $reference): Payment
    {
        $payment = $event->paymentId !== null
            ? $this->payments->find($event->paymentId)
            : $this->payments->findByReference($event->gateway, $reference);

        return $payment ?? throw new PaymentNotFoundException('payments.not_found');
    }
}

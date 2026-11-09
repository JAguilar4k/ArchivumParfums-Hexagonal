<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\UseCases;

use Src\Payments\Payments\Application\Services\PaymentSettlement;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Domain\Exceptions\PaymentEventMismatchException;
use Src\Payments\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Payments\Payments\Domain\ValueObjects\PaymentEvent;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class ApplyPaymentEvent
{
    /** Receive the ports of the payment, the settlement that locks what it pays and the transaction of the payment's business. */
    public function __construct(
        private PaymentRepository $payments,
        private PaymentSettlement $settlement,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Apply a verified notice to the payment it names, in one transaction of the business pinned for it. The notice
     * must name this payment's charge with its currency and exact amount, which only a notice of a failure may leave
     * out; then, while the payment is pending, it changes with what the provider answers to the server's own query,
     * together with its order or its decant pack. A notice about a payment that is no longer pending changes nothing.
     *
     * @throws PaymentNotFoundException when no payment of the business has the notice's references
     * @throws PaymentEventMismatchException when the notice names another charge, amount or currency
     * @throws GatewayUnavailableException when the provider cannot answer; nothing changes
     * @throws InvalidValueException when the provider answers an amount that is not the payment's
     */
    public function execute(PaymentEvent $event): void
    {
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
            }
        });
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

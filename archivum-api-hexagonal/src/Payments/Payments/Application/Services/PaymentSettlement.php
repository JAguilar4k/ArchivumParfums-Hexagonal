<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\Services;

use Src\Payments\Payments\Application\Contracts\PayablePackReader;
use Src\Payments\Payments\Application\Contracts\PayableReader;
use Src\Payments\Payments\Application\DTOs\PayableOrder;
use Src\Payments\Payments\Application\DTOs\PayablePack;
use Src\Payments\Payments\Domain\Contracts\PaymentGateways;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Domain\Exceptions\InvalidPaymentTransitionException;
use Src\Payments\Payments\Domain\ValueObjects\ChargeReference;
use Src\Payments\Payments\Domain\ValueObjects\Payable;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;

/** What follows the provider's answer about a pending charge, whoever asked: a verified notice (#94) or the reconciliation (#96). */
final readonly class PaymentSettlement
{
    /** Receive the ports a settled charge touches. */
    public function __construct(
        private PayableReader $orders,
        private PayablePackReader $packs,
        private PaymentRepository $payments,
        private PaymentGateways $gateways,
    ) {}

    /**
     * Lock what the payment pays, before the payment itself, in the same order as StartPayment: its order or, since
     * #145, its decant pack.
     */
    public function lockPayable(Payable $payable): PayableOrder|PayablePack
    {
        return $payable->type === 'decant_pack' ? $this->packs->findForUpdate($payable->id) : $this->orders->findForUpdate($payable->id);
    }

    /**
     * Inside the transaction that locked the order or the pack and then the pending payment: ask the provider, with the
     * server's own credentials, what happened to the charge, keep its identity and close the payment with that status.
     * A success adds the payment to what the order paid; one that arrives after the order was cancelled or expired is
     * kept and its refund requested. A success activates a pack that waits for it (R33). PayPal captures an approved
     * charge only while the order or the pack still waits for it.
     *
     * @throws GatewayUnavailableException when the provider cannot answer; nothing changes
     */
    public function settle(Payment $payment, PayableOrder|PayablePack $payable): void
    {
        $awaitsPayment = $payable instanceof PayablePack
            ? $payable->status === 'pending_payment'
            : in_array($payable->status, ['pending_payment', 'ready'], true);
        $reference = $payment->providerReference ?? throw new InvalidPaymentTransitionException('payments.invalid_transition');
        $result = $this->gateways->for($payment->gateway)->status(new ChargeReference(
            $payment->id->value,
            $reference,
            $payment->chargedCurrency,
            $payment->chargedAmount,
            $payment->checkoutReference,
            captureAllowed: $awaitsPayment,
        ));

        $payment->attach($result);
        $payment->apply($result->status);
        if ($payable instanceof PayablePack) {
            $this->settlePack($payment, $result->status === PaymentStatus::Succeeded && $awaitsPayment);
        } elseif ($result->status === PaymentStatus::Succeeded && in_array($payable->status, ['cancelled', 'expired'], true)) {
            // Save the confirmed principal first so its refund can reference it in this same transaction.
            $this->saveSettled($payment);
            $this->orders->applyLatePayment($payment->payable->id, $payment->amount->amount);
        } elseif ($result->status === PaymentStatus::Succeeded) {
            $this->orders->applyPayment($payment->payable->id, $payment->amount->amount);
            $this->saveSettled($payment);
        } else {
            $this->saveSettled($payment);
        }
    }

    /**
     * A confirmed charge activates the pack that waited for it, in the same transaction. One confirmed for a pack that
     * no longer waits stays recorded as it is: its full refund comes with the packs of #192.
     */
    private function settlePack(Payment $payment, bool $activates): void
    {
        if ($activates) {
            $this->packs->applyPayment($payment->payable->id);
        }

        $this->saveSettled($payment);
    }

    /**
     * Besides the locks, the row changes only while it is still pending (#95). If another operation settled the payment
     * first, the transaction is undone, together with what this one changed in the order or the pack.
     */
    private function saveSettled(Payment $payment): void
    {
        if (! $this->payments->saveIfPending($payment)) {
            throw new InvalidPaymentTransitionException('payments.invalid_transition');
        }
    }
}

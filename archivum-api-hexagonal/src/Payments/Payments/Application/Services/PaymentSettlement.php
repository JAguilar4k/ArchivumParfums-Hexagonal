<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\Services;

use Src\Payments\Payments\Application\Contracts\PayablePackReader;
use Src\Payments\Payments\Application\Contracts\PayableReader;
use Src\Payments\Payments\Application\DTOs\PayableOrder;
use Src\Payments\Payments\Application\DTOs\PayablePack;
use Src\Payments\Payments\Application\DTOs\RefundPaymentCommand;
use Src\Payments\Payments\Application\UseCases\RefundPayment;
use Src\Payments\Payments\Domain\Contracts\PaymentGateways;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Domain\Exceptions\InvalidPaymentTransitionException;
use Src\Payments\Payments\Domain\ValueObjects\ChargeReference;
use Src\Payments\Payments\Domain\ValueObjects\ChargeResult;
use Src\Payments\Payments\Domain\ValueObjects\Payable;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;

/**
 * What follows the provider's answer about a pending charge, or about one the expiry of its order or its pack closed
 * (#192), whoever asked: a verified notice (#94) or the reconciliation (#96).
 */
final readonly class PaymentSettlement
{
    /** Receive the ports a settled charge touches. */
    public function __construct(
        private PayableReader $orders,
        private PayablePackReader $packs,
        private PaymentRepository $payments,
        private PaymentGateways $gateways,
        private RefundPayment $refunds,
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
     * A success adds the payment to what the order paid and activates a pack that waits for it (R33); one that arrives
     * after the order was cancelled or expired, or after the pack expired, is kept and its refund requested. PayPal
     * captures an approved charge only while the order or the pack still waits for it.
     *
     * @throws GatewayUnavailableException when the provider cannot answer; nothing changes
     */
    public function settle(Payment $payment, PayableOrder|PayablePack $payable): void
    {
        $result = $this->status($payment, $payable);

        $payment->attach($result);
        $payment->apply($result->status);
        if ($result->status === PaymentStatus::Succeeded && $this->isClosed($payable)) {
            $this->recordLateCharge($payment, $payable);
        } elseif ($payable instanceof PayablePack) {
            $this->settlePack($payment, $result->status === PaymentStatus::Succeeded && $this->awaitsPayment($payable));
        } elseif ($result->status === PaymentStatus::Succeeded) {
            $this->orders->applyPayment($payment->payable->id, $payment->amount->amount);
            $this->saveSettled($payment);
        } else {
            $this->saveSettled($payment);
        }
    }

    /**
     * Inside the transaction that locked the order or the pack and then the expired payment: if the order was cancelled
     * or expired, or the pack expired, ask the provider again what happened to the charge, because their expiry closes
     * the payment without asking (#158, #159). Only a success changes something (#192): the payment becomes succeeded,
     * as the provider says (R39), and the charge is kept and its refund requested, as for one that arrives after a
     * cancellation. Any other answer, or an order or a pack that still waits for its payment, leaves the payment
     * expired.
     *
     * @throws GatewayUnavailableException when the provider cannot answer; nothing changes
     */
    public function settleLate(Payment $payment, PayableOrder|PayablePack $payable): void
    {
        if ($payment->status !== PaymentStatus::Expired || ! $this->isClosed($payable)) {
            return;
        }

        $result = $this->status($payment, $payable);
        if ($result->status !== PaymentStatus::Succeeded) {
            return;
        }

        $payment->confirmLate($result);
        $this->recordLateCharge($payment, $payable);
    }

    /**
     * Whether the order was cancelled or expired, or the pack expired, so that a charge confirmed now arrives late and
     * is refunded (P11). A pack that is active or exhausted was already paid.
     */
    private function isClosed(PayableOrder|PayablePack $payable): bool
    {
        return $payable instanceof PayablePack
            ? $payable->status === 'expired'
            : in_array($payable->status, ['cancelled', 'expired'], true);
    }

    /** Whether the order or the pack still waits for its payment, the only case in which PayPal captures an approved charge. */
    private function awaitsPayment(PayableOrder|PayablePack $payable): bool
    {
        return $payable instanceof PayablePack
            ? $payable->status === 'pending_payment'
            : in_array($payable->status, ['pending_payment', 'ready'], true);
    }

    /** What the provider answers, with the server's own credentials, about the charge of the payment. */
    private function status(Payment $payment, PayableOrder|PayablePack $payable): ChargeResult
    {
        $reference = $payment->providerReference ?? throw new InvalidPaymentTransitionException('payments.invalid_transition');

        return $this->gateways->for($payment->gateway)->status(new ChargeReference(
            $payment->id->value,
            $reference,
            $payment->chargedCurrency,
            $payment->chargedAmount,
            $payment->checkoutReference,
            captureAllowed: $this->awaitsPayment($payable),
        ));
    }

    /**
     * Keep a charge confirmed after the order was cancelled or expired, or after the pack expired, and request its full
     * refund (#192). Besides the locks, the row changes only while it is still pending or expired: the same notice
     * applied twice, or also by the reconciliation, registers one refund. The confirmed principal is saved first, so
     * that its refund can reference it in this same transaction. The order counts the charge in what it paid until its
     * refund is confirmed; a pack keeps no paid amount, so only its payment registers the refund, and the pack stays
     * expired.
     */
    private function recordLateCharge(Payment $payment, PayableOrder|PayablePack $payable): void
    {
        if (! $this->payments->saveIfPendingOrExpired($payment)) {
            throw new InvalidPaymentTransitionException('payments.invalid_transition');
        }

        if ($payable instanceof PayablePack) {
            $this->refunds->execute(new RefundPaymentCommand($payment->id->value, $payment->amount->amount));
        } else {
            $this->orders->applyLatePayment($payment->payable->id, $payment->amount->amount);
        }
    }

    /**
     * A confirmed charge activates the pack that waited for it, in the same transaction. One confirmed for a pack that
     * is already active or exhausted stays recorded as it is.
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

<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\Services;

use Src\Payments\Payments\Application\Contracts\PayableReader;
use Src\Payments\Payments\Application\DTOs\PayableOrder;
use Src\Payments\Payments\Domain\Contracts\PaymentGateways;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Domain\Exceptions\InvalidPaymentTransitionException;
use Src\Payments\Payments\Domain\ValueObjects\ChargeReference;
use Src\Payments\Payments\Domain\ValueObjects\ChargeResult;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;

/**
 * What follows the provider's answer about a pending charge, or about one the expiry of its order closed (#192), whoever
 * asked: a verified notice (#94) or the reconciliation (#96).
 */
final readonly class PaymentSettlement
{
    /** Receive the ports a settled charge touches. */
    public function __construct(
        private PayableReader $orders,
        private PaymentRepository $payments,
        private PaymentGateways $gateways,
    ) {}

    /**
     * Inside the transaction that locked the order and then the pending payment: ask the provider, with the server's
     * own credentials, what happened to the charge, keep its identity and close the payment with that status. A
     * success adds the payment to what the order paid; one that arrives after the order was cancelled or expired is
     * kept and its refund requested. PayPal captures an approved charge only while the order still waits for it.
     *
     * @throws GatewayUnavailableException when the provider cannot answer; nothing changes
     */
    public function settle(Payment $payment, PayableOrder $order): void
    {
        $result = $this->status($payment, $order);

        $payment->attach($result);
        $payment->apply($result->status);
        if ($result->status === PaymentStatus::Succeeded && $this->isClosed($order)) {
            $this->recordLateCharge($payment);
        } elseif ($result->status === PaymentStatus::Succeeded) {
            $this->orders->applyPayment($payment->payable->id, $payment->amount->amount);
            $this->saveSettled($payment);
        } else {
            $this->saveSettled($payment);
        }
    }

    /**
     * Inside the transaction that locked the order and then the expired payment: if the order was cancelled or expired,
     * ask the provider again what happened to the charge, because the expiry of an order closes its payment without
     * asking (#158). Only a success changes something (#192): the payment becomes succeeded, as the provider says
     * (R39), and the charge is kept and its refund requested, as for one that arrives after a cancellation. Any other
     * answer, or an order that is still open, leaves the payment expired.
     *
     * @throws GatewayUnavailableException when the provider cannot answer; nothing changes
     */
    public function settleLate(Payment $payment, PayableOrder $order): void
    {
        if ($payment->status !== PaymentStatus::Expired || ! $this->isClosed($order)) {
            return;
        }

        $result = $this->status($payment, $order);
        if ($result->status !== PaymentStatus::Succeeded) {
            return;
        }

        $payment->confirmLate($result);
        $this->recordLateCharge($payment);
    }

    /** Whether the order was cancelled or expired, so that a charge confirmed now arrives late and is refunded. */
    private function isClosed(PayableOrder $order): bool
    {
        return in_array($order->status, ['cancelled', 'expired'], true);
    }

    /** What the provider answers, with the server's own credentials, about the charge of the payment. */
    private function status(Payment $payment, PayableOrder $order): ChargeResult
    {
        $reference = $payment->providerReference ?? throw new InvalidPaymentTransitionException('payments.invalid_transition');

        return $this->gateways->for($payment->gateway)->status(new ChargeReference(
            $payment->id->value,
            $reference,
            $payment->chargedCurrency,
            $payment->chargedAmount,
            $payment->checkoutReference,
            captureAllowed: in_array($order->status, ['pending_payment', 'ready'], true),
        ));
    }

    /**
     * Keep a charge confirmed after the order was cancelled or expired and request its full refund (#192). Besides the
     * locks, the row changes only while it is still pending or expired: the same notice applied twice, or also by the
     * reconciliation, registers one refund. The confirmed principal is saved first, so that its refund can reference it
     * in this same transaction.
     */
    private function recordLateCharge(Payment $payment): void
    {
        if (! $this->payments->saveIfPendingOrExpired($payment)) {
            throw new InvalidPaymentTransitionException('payments.invalid_transition');
        }

        $this->orders->applyLatePayment($payment->payable->id, $payment->amount->amount);
    }

    /**
     * Besides the locks, the row changes only while it is still pending (#95). If another operation settled the payment
     * first, the transaction is undone, together with what this one changed in the order.
     */
    private function saveSettled(Payment $payment): void
    {
        if (! $this->payments->saveIfPending($payment)) {
            throw new InvalidPaymentTransitionException('payments.invalid_transition');
        }
    }
}

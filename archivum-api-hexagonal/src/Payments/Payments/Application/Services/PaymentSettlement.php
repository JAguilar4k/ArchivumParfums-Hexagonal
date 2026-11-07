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
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;

/** What follows the provider's answer about a pending charge, whoever asked: the customer's query or a verified notice. */
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
        $reference = $payment->providerReference ?? throw new InvalidPaymentTransitionException('payments.invalid_transition');
        $result = $this->gateways->for($payment->gateway)->status(new ChargeReference(
            $payment->id->value,
            $reference,
            $payment->chargedCurrency,
            $payment->chargedAmount,
            $payment->checkoutReference,
            captureAllowed: in_array($order->status, ['pending_payment', 'ready'], true),
        ));

        $payment->attach($result);
        $payment->apply($result->status);
        if ($result->status === PaymentStatus::Succeeded && in_array($order->status, ['cancelled', 'expired'], true)) {
            // Save the confirmed principal first so its refund can reference it in this same transaction.
            $this->payments->save($payment);
            $this->orders->applyLatePayment($payment->payable->id, $payment->amount->amount);
        } elseif ($result->status === PaymentStatus::Succeeded) {
            $this->orders->applyPayment($payment->payable->id, $payment->amount->amount);
            $this->payments->save($payment);
        } else {
            $this->payments->save($payment);
        }
    }
}

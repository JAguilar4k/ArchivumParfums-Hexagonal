<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\UseCases;

use Src\Payments\Payments\Application\Contracts\PayableReader;
use Src\Payments\Payments\Application\DTOs\PaymentResult;
use Src\Payments\Payments\Domain\Contracts\PaymentGateways;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Payments\Payments\Domain\ValueObjects\ChargeReference;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class ObservePayment
{
    public function __construct(private PayableReader $orders, private PaymentRepository $payments, private PaymentGateways $gateways, private TransactionRunner $transaction) {}

    public function execute(string $paymentId, string $userId): PaymentResult
    {
        return $this->transaction->run(function () use ($paymentId, $userId): PaymentResult {
            $payment = $this->payments->find(new PaymentId($paymentId)) ?? throw new PaymentNotFoundException('payments.not_found');
            // Lock in the same order as StartPayment: resource first, then its payment.
            $order = $this->orders->findForPayment($payment->payable->id, $userId, startingPayment: false);
            $payment = $this->payments->find($payment->id, true) ?? throw new PaymentNotFoundException('payments.not_found');
            if ($payment->status !== PaymentStatus::Pending) {
                return PaymentResult::from($payment);
            }
            if ($payment->providerReference === null) {
                return PaymentResult::from($payment, communicationError: true);
            }

            try {
                $result = $this->gateways->for($payment->gateway)->status(new ChargeReference($paymentId, $payment->providerReference, $payment->chargedCurrency, $payment->chargedAmount, $payment->checkoutReference, captureAllowed: in_array($order->status, ['pending_payment', 'ready'], true)));
            } catch (GatewayUnavailableException) {
                return PaymentResult::from($payment, communicationError: true);
            }

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

            return PaymentResult::from($payment);
        });
    }
}

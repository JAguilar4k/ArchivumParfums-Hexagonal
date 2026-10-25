<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\UseCases;

use Src\Payments\Payments\Application\Contracts\PayableReader;
use Src\Payments\Payments\Application\DTOs\PaymentResult;
use Src\Payments\Payments\Domain\Contracts\PaymentGateways;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Domain\Exceptions\InvalidPaymentTransitionException;
use Src\Payments\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Payments\Payments\Domain\ValueObjects\Payable;
use Src\Payments\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Payments\Payments\Domain\ValueObjects\PaymentPurpose;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;

final readonly class StartPayment
{
    public function __construct(private PayableReader $orders, private PaymentRepository $payments, private PaymentGateways $gateways, private TransactionRunner $transaction) {}

    public function execute(string $orderId, string $userId, PaymentMethod $method = PaymentMethod::Card): PaymentResult
    {
        $attempt = $this->transaction->run(function () use ($orderId, $userId, $method): Payment|PaymentResult {
            $order = $this->orders->findForPayment($orderId, $userId);
            if (! in_array($order->status, ['pending_payment', 'ready'], true)) {
                throw new InvalidPaymentTransitionException('payments.not_payable');
            }
            if ($order->refundPending) {
                throw new InvalidPaymentTransitionException('payments.refund_pending');
            }
            $purpose = $order->status === 'pending_payment' ? ($order->hasBottles ? PaymentPurpose::Deposit : PaymentPurpose::Full) : PaymentPurpose::Balance;
            $payable = new Payable('order', $orderId);
            $pending = $this->payments->pending($payable, $purpose);

            if ($pending !== null) {
                return PaymentResult::from($pending, communicationError: $pending->providerReference === null);
            }

            $amount = match ($order->status) {
                'pending_payment' => max(0, $order->dueNow - $order->paid),
                default => max(0, $order->subtotal - $order->paid),
            };
            if ($amount === 0) {
                throw new InvalidPaymentTransitionException('payments.not_payable');
            }
            if ($order->gateway === 'paypal' && ($order->exchangeRate === null || (float) $order->exchangeRate <= 0)) {
                throw new InvalidValueException('payments.invalid_exchange_rate');
            }

            $gateway = $this->gateways->for($order->gateway);
            if (! in_array($method, $gateway->supportedMethods(), true)) {
                throw new InvalidValueException('payments.unsupported_method');
            }

            $payment = Payment::create($this->payments->nextId(), new BusinessId($order->businessId), $payable, $purpose, new Colones($amount), $order->gateway, $method, $order->exchangeRate);
            $gateway->validateCharge(new ChargeRequest($payment->id->value, $order->businessId, $amount, $method, $order->exchangeRate));
            $this->payments->save($payment);

            return $payment;
        });

        if ($attempt instanceof PaymentResult) {
            return $attempt;
        }

        // The attempt is committed before the remote POST: losing its response cannot enable a second charge.
        try {
            $result = $this->gateways->for($attempt->gateway)->create(new ChargeRequest($attempt->id->value, $attempt->businessId->value, $attempt->amount->amount, $attempt->method, $attempt->exchangeRate));
        } catch (GatewayUnavailableException) {
            return PaymentResult::from($attempt, true, true);
        }

        return $this->transaction->run(function () use ($attempt, $result): PaymentResult {
            $payment = $this->payments->find($attempt->id, true) ?? throw new InvalidPaymentTransitionException('payments.unknown_outcome');
            $payment->attach($result);
            if (in_array($result->status, [PaymentStatus::Failed, PaymentStatus::Expired], true)) {
                $payment->apply($result->status);
            }
            // Creation alone cannot mark an order paid; ObservePayment makes the authoritative query.
            $this->payments->save($payment);

            return PaymentResult::from($payment, true);
        });
    }
}

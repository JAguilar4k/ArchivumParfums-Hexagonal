<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\UseCases;

use Src\Payments\Payments\Application\Contracts\PayablePackReader;
use Src\Payments\Payments\Application\Contracts\PayableReader;
use Src\Payments\Payments\Application\DTOs\PayableCharge;
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
    public function __construct(private PayableReader $orders, private PayablePackReader $packs, private PaymentRepository $payments, private PaymentGateways $gateways, private TransactionRunner $transaction) {}

    /**
     * Start the payment of an order (#92) or of a decant pack (#145) for the customer who owns it, with the amount the
     * server keeps and never one the browser sends, or answer the attempt of the same purpose that is still pending.
     */
    public function execute(Payable $payable, string $userId, PaymentMethod $method = PaymentMethod::Card): PaymentResult
    {
        $attempt = $this->transaction->run(function () use ($payable, $userId, $method): Payment|PaymentResult {
            $charge = $payable->type === 'decant_pack' ? $this->packCharge($payable->id, $userId) : $this->orderCharge($payable->id, $userId);
            $pending = $this->payments->pending($payable, $charge->purpose);

            if ($pending !== null) {
                return PaymentResult::from($pending, communicationError: $pending->providerReference === null);
            }

            if ($charge->amount === 0) {
                throw new InvalidPaymentTransitionException('payments.not_payable');
            }
            if ($charge->gateway === 'paypal' && ($charge->exchangeRate === null || (float) $charge->exchangeRate <= 0)) {
                throw new InvalidValueException('payments.invalid_exchange_rate');
            }

            $gateway = $this->gateways->for($charge->gateway);
            if (! in_array($method, $gateway->supportedMethods(), true)) {
                throw new InvalidValueException('payments.unsupported_method');
            }

            $payment = Payment::create($this->payments->nextId(), new BusinessId($charge->businessId), $payable, $charge->purpose, new Colones($charge->amount), $charge->gateway, $method, $charge->exchangeRate);
            $gateway->validateCharge(new ChargeRequest($payment->id->value, $charge->businessId, $charge->amount, $method, $charge->exchangeRate));
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
            // Creation alone cannot mark an order or a pack paid; a verified notice (#94) or the reconciliation (#96) asks the provider.
            $this->payments->save($payment);

            return PaymentResult::from($payment, true);
        });
    }

    /** What a locked order owes now: its deposit or its total while it waits for its payment, its balance once ready. */
    private function orderCharge(string $orderId, string $userId): PayableCharge
    {
        $order = $this->orders->findForPayment($orderId, $userId);
        if (! in_array($order->status, ['pending_payment', 'ready'], true)) {
            throw new InvalidPaymentTransitionException('payments.not_payable');
        }
        if ($order->refundPending) {
            throw new InvalidPaymentTransitionException('payments.refund_pending');
        }
        $purpose = $order->status === 'pending_payment' ? ($order->hasBottles ? PaymentPurpose::Deposit : PaymentPurpose::Full) : PaymentPurpose::Balance;
        $amount = match ($order->status) {
            'pending_payment' => max(0, $order->dueNow - $order->paid),
            default => max(0, $order->subtotal - $order->paid),
        };

        return new PayableCharge($order->businessId, $purpose, $amount, $order->gateway, $order->exchangeRate);
    }

    /** What a locked decant pack owes: the full price of its offer, only while it waits for its payment (R33). */
    private function packCharge(string $packId, string $userId): PayableCharge
    {
        $pack = $this->packs->findForPayment($packId, $userId);
        if ($pack->status !== 'pending_payment') {
            throw new InvalidPaymentTransitionException('payments.not_payable');
        }

        return new PayableCharge($pack->businessId, PaymentPurpose::Full, $pack->price, $pack->gateway, $pack->exchangeRate);
    }
}

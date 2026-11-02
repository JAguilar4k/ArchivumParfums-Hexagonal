<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\UseCases;

use Src\Payments\Payments\Application\Contracts\PayableReader;
use Src\Payments\Payments\Application\DTOs\PaymentResult;
use Src\Payments\Payments\Application\Services\PaymentSettlement;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class ObservePayment
{
    public function __construct(private PayableReader $orders, private PaymentRepository $payments, private PaymentSettlement $settlement, private TransactionRunner $transaction) {}

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
                $this->settlement->settle($payment, $order);
            } catch (GatewayUnavailableException) {
                return PaymentResult::from($payment, communicationError: true);
            }

            return PaymentResult::from($payment);
        });
    }
}

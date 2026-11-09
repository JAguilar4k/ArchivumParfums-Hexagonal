<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\UseCases;

use Src\Payments\Payments\Application\Contracts\PayableReader;
use Src\Payments\Payments\Application\DTOs\PaymentResult;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class ObservePayment
{
    /** Receive the ports that read the payment and tell whose order it pays. */
    public function __construct(private PayableReader $orders, private PaymentRepository $payments, private TransactionRunner $transaction) {}

    /**
     * Read the stored payment for the customer whose order it pays. The query never asks the provider and never changes
     * the payment (R38): a verified notice (#94) or the reconciliation (#96) closes it, and while it stays pending the
     * client shows "procesando". A pending payment with no reference of the provider still does not know whether its
     * charge was created, so the answer asks to come back later, as the creation did.
     */
    public function execute(string $paymentId, string $userId): PaymentResult
    {
        return $this->transaction->run(function () use ($paymentId, $userId): PaymentResult {
            $payment = $this->payments->find(new PaymentId($paymentId)) ?? throw new PaymentNotFoundException('payments.not_found');
            if (! $this->orders->isOwnedBy($payment->payable->id, $userId)) {
                throw new PaymentNotFoundException('payments.not_found');
            }

            return PaymentResult::from($payment, communicationError: $payment->status === PaymentStatus::Pending && $payment->providerReference === null);
        });
    }
}

<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\UseCases;

use Src\Payments\Payments\Application\Contracts\BusinessScope;
use Src\Payments\Payments\Application\DTOs\ReconcilePaymentCommand;
use Src\Payments\Payments\Application\Services\PaymentSettlement;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class ReconcilePayment
{
    /** Receive the ports that pin the payment's business, lock what it pays and the payment, and settle the charge. */
    public function __construct(
        private BusinessScope $scope,
        private PaymentRepository $payments,
        private PaymentSettlement $settlement,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Ask the provider what happened to a pending payment and close it with the same settlement that a verified notice
     * uses (#94), in one transaction of the payment's business; answer whether the payment left pending. A payment that
     * a notice already closed changes nothing, and a notice that arrives after the reconciliation finds the payment
     * closed and changes nothing either.
     *
     * @throws GatewayUnavailableException when the provider cannot answer; the payment stays pending
     */
    public function execute(ReconcilePaymentCommand $command): bool
    {
        return $this->scope->run(new BusinessId($command->businessId), fn (): bool => $this->transaction->run(function () use ($command): bool {
            $payment = $this->payments->find(new PaymentId($command->paymentId));
            if ($payment === null) {
                return false;
            }

            // Lock in the same order as StartPayment and ApplyPaymentEvent: the order or the pack first, then its payment.
            $payable = $this->settlement->lockPayable($payment->payable);
            $payment = $this->payments->find($payment->id, true);
            if ($payment === null || $payment->status !== PaymentStatus::Pending || $payment->providerReference === null) {
                return false;
            }

            $this->settlement->settle($payment, $payable);

            return $payment->status !== PaymentStatus::Pending;
        }));
    }
}

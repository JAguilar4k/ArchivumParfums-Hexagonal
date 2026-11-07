<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\UseCases;

use Src\Payments\Payments\Application\Contracts\OutgoingRefunds;
use Src\Payments\Payments\Application\DTOs\RefundPaymentCommand;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Contracts\RefundRepository;
use Src\Payments\Payments\Domain\Exceptions\InvalidPaymentTransitionException;
use Src\Payments\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Shared\Domain\Contracts\AuditTrail;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\AuditAction;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class RefundPayment
{
    /**
     * Receive the ports of the payment, its refunds, the queue that sends them, the trail of sensitive changes and the
     * transaction of the business.
     */
    public function __construct(
        private PaymentRepository $payments,
        private RefundRepository $refunds,
        private OutgoingRefunds $outgoing,
        private AuditTrail $audit,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Register a refund of a confirmed payment, tied to that payment, and leave its request to the provider in the queue,
     * both in one transaction (R40). The payment's row is locked before its pending refunds are counted, so two refunds
     * of the same payment never return more than it paid and has not returned yet (R30, R31). Nothing leaves for the
     * provider here. The refund leaves its entry in the trail in the same transaction, with the person of the request
     * as its author, so a refund whose entry fails is not kept (#113). Answer the refund's identifier.
     *
     * @throws PaymentNotFoundException
     * @throws InvalidPaymentTransitionException when the payment is not confirmed or cannot return that amount
     */
    public function execute(RefundPaymentCommand $command): string
    {
        return $this->transaction->run(function () use ($command): string {
            $payment = $this->payments->find(new PaymentId($command->paymentId), true) ?? throw new PaymentNotFoundException('payments.not_found');
            $refund = $payment->requestRefund($this->refunds->nextId()->value, $command->amount, $this->refunds->pendingAmountOf($payment->id), $command->orderLineId);

            $this->refunds->add($refund);
            $this->audit->record(new BusinessId($refund->businessId), AuditAction::RefundRequested, 'refund', $refund->id, null, [
                'payment_id' => $refund->paymentId, 'order_id' => $payment->payable->id, 'order_line_id' => $refund->orderLineId, 'amount' => $refund->amount,
            ]);
            $this->outgoing->send($refund->id, $refund->businessId);

            return $refund->id;
        });
    }
}

<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\UseCases;

use DateTimeImmutable;
use Src\Payments\Payments\Application\Contracts\PayableReader;
use Src\Payments\Payments\Application\Contracts\PaymentRecorders;
use Src\Payments\Payments\Application\DTOs\ManualPaymentResult;
use Src\Payments\Payments\Application\DTOs\RecordManualPaymentCommand;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Domain\Exceptions\InvalidPaymentTransitionException;
use Src\Payments\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Payments\Payments\Domain\ValueObjects\ManualPaymentMethod;
use Src\Payments\Payments\Domain\ValueObjects\ManualRecord;
use Src\Payments\Payments\Domain\ValueObjects\Payable;
use Src\Payments\Payments\Domain\ValueObjects\PaymentPurpose;
use Src\Shared\Domain\Contracts\AuditTrail;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\AuditAction;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;
use Src\Shared\Domain\ValueObjects\MemberId;

final readonly class RecordManualPayment
{
    /**
     * Receive the ports of the order, its payments, the owners who record them, the trail of sensitive changes and the
     * transaction of the business.
     */
    public function __construct(
        private PayableReader $orders,
        private PaymentRepository $payments,
        private PaymentRecorders $recorders,
        private AuditTrail $audit,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Record the balance that the customer paid the owner on delivery, by SINPE or in cash (R29), as a confirmed
     * payment, and take it off the balance of the order in the same transaction, through the port that a verified
     * notice uses. The order is locked before its balance is read, so two records of the same balance wait for each
     * other and the second one finds what the first left. Nothing goes through a gateway. The entry of the trail is
     * written in the same transaction, so a payment whose entry fails is not kept (#113).
     *
     * @throws PaymentNotFoundException when the order is not one of the business
     * @throws InvalidPaymentTransitionException when the order is not ready or owes nothing, when a refund or an online
     *                                           payment of its balance is still pending, or when the amount is greater
     *                                           than the balance
     */
    public function execute(RecordManualPaymentCommand $command): ManualPaymentResult
    {
        return $this->transaction->run(function () use ($command): ManualPaymentResult {
            $recorder = $this->recorders->ownerOf($command->userId);
            // Lock in the same order as every payment: the order first, then its payments.
            $order = $this->orders->findForUpdate($command->orderId);
            $payable = new Payable('order', $order->id);
            $balance = $order->subtotal - $order->paid;

            // R29: the balance is paid on delivery, so only a ready order that still owes money accepts it.
            if ($order->status !== 'ready' || $balance <= 0) {
                throw new InvalidPaymentTransitionException('payments.not_payable');
            }
            // R29: money that is being returned cannot pay the balance.
            if ($order->refundPending) {
                throw new InvalidPaymentTransitionException('payments.refund_pending');
            }
            // The customer may be paying the same balance online, and its confirmation would then exceed the balance.
            if ($this->payments->pending($payable, PaymentPurpose::Balance) !== null) {
                throw new InvalidPaymentTransitionException('payments.online_payment_pending');
            }
            if ($command->amount > $balance) {
                throw new InvalidPaymentTransitionException('payments.over_balance');
            }

            $payment = Payment::recordManually($this->payments->nextId(), new BusinessId($order->businessId), $payable, new Colones($command->amount), new ManualRecord(
                new MemberId($recorder->memberId),
                ManualPaymentMethod::from($command->method),
                $command->reference,
                new DateTimeImmutable($command->recordedAt),
            ));
            $this->orders->applyPayment($order->id, $payment->amount->amount);
            $this->payments->save($payment);
            $this->audit->record($payment->businessId, AuditAction::ManualPaymentRecorded, 'payment', $payment->id->value, null, [
                'order_id' => $order->id, 'method' => $command->method, 'amount' => $command->amount,
            ]);

            return ManualPaymentResult::from($payment, $recorder);
        });
    }
}

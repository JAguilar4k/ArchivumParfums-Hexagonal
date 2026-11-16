<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\Services;

use Src\Payments\Payments\Application\Contracts\PayablePackReader;
use Src\Payments\Payments\Application\Contracts\PayableReader;
use Src\Payments\Payments\Domain\Contracts\PaymentGateways;
use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\Contracts\RefundRepository;
use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Domain\Entities\Refund;
use Src\Payments\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Payments\Payments\Domain\Exceptions\InvalidPaymentTransitionException;
use Src\Payments\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Payments\Payments\Domain\ValueObjects\RefundId;
use Src\Payments\Payments\Domain\ValueObjects\RefundRequest;
use Src\Payments\Payments\Domain\ValueObjects\RefundResult;

/**
 * What follows the provider's answer about a pending refund, whoever asked for it: the job that sent the refund, a
 * verified notice of the provider or the reconciliation (#97).
 */
final readonly class RefundSettlement
{
    /** Receive the ports a settled refund touches. */
    public function __construct(
        private PayableReader $orders,
        private PayablePackReader $packs,
        private PaymentRepository $payments,
        private RefundRepository $refunds,
        private PaymentGateways $gateways,
    ) {}

    /**
     * Lock a refund the way every operation on a payment locks: the order or, for the late charge of an expired pack
     * (#192), the pack first, then its payment, then the refund. Answer null when the business has no such refund.
     *
     * @return array{Refund, Payment}|null
     */
    public function lock(RefundId $id): ?array
    {
        $refund = $this->refunds->find($id);
        if ($refund === null) {
            return null;
        }

        $payment = $this->payments->find(new PaymentId($refund->paymentId)) ?? throw new PaymentNotFoundException('payments.not_found');
        if ($payment->payable->type === 'decant_pack') {
            $this->packs->findForUpdate($payment->payable->id);
        } else {
            $this->orders->findForUpdate($payment->payable->id);
        }
        $payment = $this->payments->find($payment->id, true) ?? throw new PaymentNotFoundException('payments.not_found');
        $refund = $this->refunds->find($id, true) ?? throw new PaymentNotFoundException('payments.not_found');

        return [$refund, $payment];
    }

    /**
     * Inside the transaction that locked a pending refund the provider already accepted: ask the provider, with the
     * server's own credentials, what happened to it, and apply that answer.
     *
     * @throws GatewayUnavailableException when the provider cannot answer; nothing changes
     */
    public function settle(Refund $refund, Payment $payment): void
    {
        $this->apply($refund, $payment, $this->gateways->for($payment->gateway)->refund($this->requestFor($refund, $payment)));
    }

    /**
     * Apply the provider's answer to a locked pending refund. A confirmed refund returns its colones from the payment,
     * which becomes refunded or partially refunded, and from what the order paid (R40); a pack keeps no paid amount and
     * stays as it is. A rejected one leaves both as they were, and one still pending keeps the provider's reference.
     * Besides the locks, the refund leaves pending only while its row is still pending, so two answers never apply it
     * twice.
     */
    public function apply(Refund $refund, Payment $payment, RefundResult $answer): void
    {
        $refund->record($answer->providerReference, $answer->status);

        if ($refund->status === 'pending') {
            $this->refunds->save($refund);

            return;
        }

        if (! $this->refunds->saveIfPending($refund)) {
            throw new InvalidPaymentTransitionException('payments.invalid_refund');
        }

        if ($refund->status === 'succeeded') {
            $payment->refund($refund->amount);
            $this->payments->save($payment);
            if ($payment->payable->type === 'order') {
                $this->orders->applyRefund($payment->payable->id, $refund->amount);
            }
        }
    }

    /** What the provider needs to know about a refund that left for it: its identities, its amount and the charge it returns. */
    public function requestFor(Refund $refund, Payment $payment): RefundRequest
    {
        return new RefundRequest(
            $refund->id,
            $refund->businessId,
            $payment->providerReference ?? throw new InvalidPaymentTransitionException('payments.invalid_refund'),
            $refund->chargedAmount ?? throw new InvalidPaymentTransitionException('payments.invalid_refund'),
            $payment->captureReference,
            $refund->providerReference,
        );
    }
}

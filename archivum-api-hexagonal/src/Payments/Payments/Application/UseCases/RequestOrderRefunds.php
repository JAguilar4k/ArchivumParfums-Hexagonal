<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\UseCases;

use Src\Payments\Payments\Application\Contracts\OutgoingRefunds;
use Src\Payments\Payments\Application\Contracts\PayableReader;
use Src\Payments\Payments\Application\DTOs\OrderRefund;
use Src\Payments\Payments\Domain\Contracts\RefundRepository;
use Src\Payments\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class RequestOrderRefunds
{
    /** Receive the ports of the order, its refunds, the queue that sends them and the transaction of the business. */
    public function __construct(
        private PayableReader $orders,
        private RefundRepository $refunds,
        private OutgoingRefunds $outgoing,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Ask the provider again for the refunds of an order that never left, as when their job ran out of attempts, and
     * answer every refund of the order with its state, so the owner sees which ones the provider confirmed or rejected.
     * Only a cancellation, an unavailable line (R30, R31) or a charge confirmed after the order closed registers a
     * refund, always for the amount the server calculates: the panel never decides one, because section 12 of the
     * specification admits no other refunds.
     *
     * @return list<OrderRefund>
     *
     * @throws PaymentNotFoundException when the order is not of the business
     */
    public function execute(string $orderId): array
    {
        return $this->transaction->run(function () use ($orderId): array {
            $this->orders->findForUpdate($orderId);
            $refunds = $this->refunds->ofOrder($orderId);

            foreach ($refunds as $refund) {
                if ($refund->status === 'pending' && ! $refund->attempted) {
                    $this->outgoing->send($refund->id, $refund->businessId);
                }
            }

            return array_map(OrderRefund::from(...), $refunds);
        });
    }
}

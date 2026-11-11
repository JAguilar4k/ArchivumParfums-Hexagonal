<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Sales;

use Src\Payments\Payments\Domain\Contracts\PaymentRepository;
use Src\Payments\Payments\Domain\ValueObjects\Payable;
use Src\Payments\Payments\Domain\ValueObjects\PaymentPurpose;
use Src\Payments\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Sales\Orders\Application\Contracts\OrderPayments;

final readonly class PaymentOrderPayments implements OrderPayments
{
    public function __construct(private PaymentRepository $payments) {}

    /**
     * Inside the transaction that locked the expiring order: a payment that still waits for the provider becomes
     * expired with UPDATE ... WHERE status = 'pending', so it changes only if nothing settled it first (R25). A notice
     * of the provider that arrives later finds it closed.
     */
    public function expirePending(string $orderId): void
    {
        foreach (PaymentPurpose::cases() as $purpose) {
            $payment = $this->payments->pending(new Payable('order', $orderId), $purpose);

            if ($payment !== null) {
                $payment->apply(PaymentStatus::Expired);
                $this->payments->saveIfPending($payment);
            }
        }
    }
}

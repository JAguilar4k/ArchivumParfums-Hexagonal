<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\Contracts;

use Src\Payments\Payments\Domain\Entities\Payment;
use Src\Payments\Payments\Domain\ValueObjects\Payable;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Payments\Payments\Domain\ValueObjects\PaymentPurpose;

interface PaymentRepository
{
    public function nextId(): PaymentId;

    public function find(PaymentId $id, bool $forUpdate = false): ?Payment;

    public function pending(Payable $payable, PaymentPurpose $purpose): ?Payment;

    /** The last attempt made to pay an order or a decant pack of the business, whatever its status. */
    public function last(Payable $payable): ?Payment;

    /** Find the payment of the business that a reference of the provider names: its charge, checkout or capture. */
    public function findByReference(string $gateway, string $reference): ?Payment;

    public function save(Payment $payment): void;

    /**
     * Save a payment whose charge was settled, only while its row is still pending: two operations that settle the
     * same payment change it once. Answer false when the row was no longer pending, and then nothing changes.
     */
    public function saveIfPending(Payment $payment): bool;

    /**
     * Save a payment whose charge the provider confirmed after its order was cancelled or expired (#192), only while its
     * row is still pending or expired: the late charge changes the payment, and registers its refund, only once. Answer
     * false when the row was neither, and then nothing changes.
     */
    public function saveIfPendingOrExpired(Payment $payment): bool;
}

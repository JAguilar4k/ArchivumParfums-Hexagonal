<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\Contracts;

use Src\Payments\Payments\Domain\Entities\Refund;
use Src\Payments\Payments\Domain\ValueObjects\PaymentId;
use Src\Payments\Payments\Domain\ValueObjects\RefundId;

/** The refunds of the payments of the current business. Every change to a refund locks its payment first. */
interface RefundRepository
{
    public function nextId(): RefundId;

    /** Find a refund; with $forUpdate, its row stays locked until the transaction ends. */
    public function find(RefundId $id, bool $forUpdate = false): ?Refund;

    /** Find the refund of a payment of the gateway that a reference of the provider names. */
    public function findByReference(string $gateway, string $reference): ?Refund;

    /** Colones of the refunds of a payment that are still pending. */
    public function pendingAmountOf(PaymentId $paymentId): int;

    /** Colones of the pending refunds of a payment that already left for the provider. */
    public function inFlightAmountOf(PaymentId $paymentId): int;

    /**
     * The refunds of the payments of an order, oldest first.
     *
     * @return list<Refund>
     */
    public function ofOrder(string $orderId): array;

    public function add(Refund $refund): void;

    /** Save the attempt and the provider's reference of a refund that is still pending. */
    public function save(Refund $refund): void;

    /**
     * Save a refund that stopped being pending, only while its row is still pending: two operations that settle the
     * same refund change it once. Answer false when the row was no longer pending, and then nothing changes.
     */
    public function saveIfPending(Refund $refund): bool;
}

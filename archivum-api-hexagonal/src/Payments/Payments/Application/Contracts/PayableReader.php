<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\Contracts;

use Src\Payments\Payments\Application\DTOs\PayableOrder;

interface PayableReader
{
    public function findForPayment(string $orderId, string $userId, bool $startingPayment = true): PayableOrder;

    /** Whether the order belongs to this user as a customer of the current business; it only reads, without locking it. */
    public function isOwnedBy(string $orderId, string $userId): bool;

    /** Lock the order a payment pays, with no customer: a verified notice of the provider confirms it, not a person. */
    public function findForUpdate(string $orderId): PayableOrder;

    public function applyPayment(string $orderId, int $amount): void;

    public function applyRefund(string $orderId, int $amount): void;

    public function applyLatePayment(string $orderId, int $amount): void;
}

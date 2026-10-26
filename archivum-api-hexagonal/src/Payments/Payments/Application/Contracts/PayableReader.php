<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\Contracts;

use Src\Payments\Payments\Application\DTOs\PayableOrder;

interface PayableReader
{
    public function findForPayment(string $orderId, string $userId, bool $startingPayment = true): PayableOrder;

    public function applyPayment(string $orderId, int $amount): void;

    public function applyRefund(string $orderId, int $amount): void;

    public function applyLatePayment(string $orderId, int $amount): void;
}

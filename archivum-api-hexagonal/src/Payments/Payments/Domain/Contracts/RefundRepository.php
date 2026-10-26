<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\Contracts;

use Src\Payments\Payments\Domain\Entities\Refund;

interface RefundRepository
{
    /**
     * Lock the payable before its payments and refunds, within the current business.
     *
     * @return list<Refund>
     */
    public function pendingForOrder(string $orderId): array;

    public function save(Refund $refund): void;
}

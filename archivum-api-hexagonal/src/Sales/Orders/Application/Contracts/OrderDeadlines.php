<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\Contracts;

use DateTimeImmutable;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;
use Src\Shared\Domain\ValueObjects\BusinessId;

interface OrderDeadlines
{
    /** Check at the given moment whether the order is still waiting for its payment, and expire it if so (R25). */
    public function checkAt(OrderId $orderId, BusinessId $businessId, DateTimeImmutable $moment): void;
}

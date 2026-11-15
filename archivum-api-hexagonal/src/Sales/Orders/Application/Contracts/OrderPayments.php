<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\Contracts;

interface OrderPayments
{
    /** Close as expired the payment of the order that still waits for the provider, if there is one (R25). */
    public function expirePending(string $orderId): void;
}

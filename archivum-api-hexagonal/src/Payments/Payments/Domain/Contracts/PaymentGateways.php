<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\Contracts;

interface PaymentGateways
{
    public function for(string $gateway): PaymentGateway;
}

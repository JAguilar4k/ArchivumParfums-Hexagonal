<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\Contracts;

use Src\Shared\Domain\ValueObjects\BusinessId;

interface PaymentBusinesses
{
    /** Find the business of a reference of the provider, before any business is pinned; null when no payment has it. */
    public function ofReference(string $gateway, string $reference): ?BusinessId;
}

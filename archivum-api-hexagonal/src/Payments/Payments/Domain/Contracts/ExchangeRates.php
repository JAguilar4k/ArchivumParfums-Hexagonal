<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\Contracts;

use Src\Payments\Payments\Domain\ValueObjects\ExchangeRate;

interface ExchangeRates
{
    public function current(): ?ExchangeRate;
}

<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\GatewaySelection;

use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Payments\Payments\Domain\Contracts\ExchangeRates;
use Src\Payments\Payments\Domain\ValueObjects\ExchangeRate;
use Src\Shared\Domain\Contracts\BusinessContext;

final readonly class BusinessExchangeRates implements ExchangeRates
{
    public function __construct(private BusinessRepository $businesses, private BusinessContext $business) {}

    public function current(): ?ExchangeRate
    {
        $rate = $this->businesses->find($this->business->current())?->settings->usdExchangeRate;

        return $rate === null ? null : new ExchangeRate(number_format($rate, 6, '.', ''));
    }
}

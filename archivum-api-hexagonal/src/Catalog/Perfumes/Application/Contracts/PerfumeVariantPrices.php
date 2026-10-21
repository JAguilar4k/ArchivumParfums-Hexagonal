<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Application\Contracts;

use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeVariantId;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;

interface PerfumeVariantPrices
{
    /**
     * Give every presentation of the business that has no manual price the sale price the callback answers for it,
     * and report how many prices changed. The presentations of other businesses are never read.
     *
     * @param  callable(PerfumeVariantId): ?Colones  $priceOf
     */
    public function recalculateWithoutManualPrice(BusinessId $business, callable $priceOf): int;
}

<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Application\UseCases;

use Src\Catalog\Perfumes\Application\Contracts\PerfumeVariantPrices;
use Src\Catalog\Perfumes\Domain\Contracts\VariantCost;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeVariantId;
use Src\Catalog\Perfumes\Domain\ValueObjects\SalePrice;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;

final readonly class RecalculateSalePrices
{
    /** Receive the stored prices, the costs and the transaction ports. */
    public function __construct(
        private PerfumeVariantPrices $prices,
        private VariantCost $costs,
        private TransactionRunner $transaction,
    ) {}

    /** Recalculate the sale price of every presentation of the business without a manual price, for a new margin; return how many changed. */
    public function execute(string $businessId, int $marginColones): int
    {
        $margin = new Colones($marginColones);

        return $this->transaction->run(fn (): int => $this->prices->recalculateWithoutManualPrice(
            new BusinessId($businessId),
            fn (PerfumeVariantId $variant): ?Colones => SalePrice::calculate($this->costs->costOf($variant), $margin, null),
        ));
    }
}

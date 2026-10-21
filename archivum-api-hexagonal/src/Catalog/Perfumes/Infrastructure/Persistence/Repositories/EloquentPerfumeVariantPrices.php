<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Persistence\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Src\Catalog\Perfumes\Application\Contracts\PerfumeVariantPrices;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeVariantId;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeVariantModel;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;

final readonly class EloquentPerfumeVariantPrices implements PerfumeVariantPrices
{
    /**
     * Update, in chunks, only the presentations of the business that have no manual price, and only the ones whose price changes.
     *
     * Each write checks again, in the same UPDATE, that the presentation still has no manual price: a manual price
     * committed after the chunk was read makes PostgreSQL skip the row, so it is never overwritten (R1) nor counted.
     */
    public function recalculateWithoutManualPrice(BusinessId $business, callable $priceOf): int
    {
        $changed = 0;

        PerfumeVariantModel::query()
            ->where('business_id', $business->value)
            ->whereNull('sale_price_override')
            ->chunkById(200, function (Collection $variants) use ($business, $priceOf, &$changed): void {
                foreach ($variants as $variant) {
                    $price = $priceOf(new PerfumeVariantId($variant->id));
                    $amount = $price instanceof Colones ? $price->amount : null;

                    if ($variant->sale_price !== $amount) {
                        $changed += PerfumeVariantModel::query()
                            ->where('business_id', $business->value)
                            ->where('id', $variant->id)
                            ->whereNull('sale_price_override')
                            ->update(['sale_price' => $amount]);
                    }
                }
            });

        return $changed;
    }
}

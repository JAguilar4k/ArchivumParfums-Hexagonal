<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Infrastructure\Catalog;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Decants\Shared\Domain\ValueObjects\DecantPrice;
use Src\Sales\Orders\Application\Contracts\OrderProducts;
use Src\Shared\Domain\Contracts\BusinessContext;

final readonly class CatalogOrderProducts implements OrderProducts
{
    public function __construct(private BusinessContext $business) {}

    /** Quote current prices in this business; untrusted line identifiers never leak another tenant. */
    public function quote(string $kind, string $reference, ?int $ml, int $lineIndex): array
    {
        $business = $this->business->current()->value;
        $query = DB::table('perfume_variants as variants')
            ->join('perfumes', function (JoinClause $join): void {
                $join->on('perfumes.id', '=', 'variants.perfume_id')->on('perfumes.business_id', '=', 'variants.business_id');
            })
            ->where('variants.business_id', $business)->where('variants.is_active', true)->where('perfumes.is_active', true)->whereNotNull('variants.sale_price');

        if ($kind === 'bottle') {
            $row = $query->where('variants.id', $reference)->select(['variants.id', 'variants.sale_price'])->first();

            if ($row === null) {
                throw ValidationException::withMessages(['lineas.'.$lineIndex.'.presentacion_id' => __('orders.product_unavailable')]);
            }

            return ['price' => (int) $row->sale_price, 'variant_id' => (string) $row->id];
        }

        $settings = BusinessModel::query()->findOrFail($business)->settings;
        $sizes = $settings['decant_sizes_ml'] ?? [2, 5, 10];

        if (! is_array($sizes) || ! in_array($ml, $sizes, true)) {
            throw ValidationException::withMessages(['lineas.'.$lineIndex.'.ml' => __('orders.decant_size')]);
        }

        $row = $query->join('decant_bottles as bottles', function (JoinClause $join): void {
            $join->on('bottles.perfume_variant_id', '=', 'variants.id')->on('bottles.business_id', '=', 'variants.business_id');
        })->where('bottles.id', $reference)->where('bottles.status', 'open')
            ->select(['variants.id', 'variants.sale_price', 'variants.size_ml'])->first();

        if ($row === null || $ml === null) {
            throw ValidationException::withMessages(['lineas.'.$lineIndex.'.botella_id' => __('orders.product_unavailable')]);
        }

        return ['price' => DecantPrice::calculate((int) $row->sale_price, (int) $row->size_ml, $ml, (int) ($settings['decant_rounding_colones'] ?? 100)), 'variant_id' => (string) $row->id];
    }

    public function settings(): array
    {
        $settings = BusinessModel::query()->findOrFail($this->business->current()->value)->settings;

        return ['deposit_percent' => (int) ($settings['bottle_deposit_percent'] ?? 50), 'expiry_minutes' => (int) ($settings['unpaid_expiry_minutes'] ?? 30)];
    }
}

<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $business_id
 * @property string $perfume_id
 * @property int $size_ml
 * @property int|null $sale_price
 * @property int|null $sale_price_override
 * @property bool $is_active
 */
#[Table('perfume_variants')]
#[Fillable(['id', 'business_id', 'perfume_id', 'size_ml', 'sale_price', 'sale_price_override', 'is_active'])]
final class PerfumeVariantModel extends Model
{
    use HasUuids;

    /**
     * Cast the columns to native types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_ml' => 'integer',
            'sale_price' => 'integer',
            'sale_price_override' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}

<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Persistence\Models;

use Database\Factories\DecantPackPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $business_id
 * @property string $name
 * @property int $decants_count
 * @property int $ml_per_decant
 * @property int $price
 * @property int $validity_days
 * @property int|null $max_price_per_ml
 * @property bool $is_active
 */
#[Table('decant_pack_plans')]
#[Fillable(['id', 'business_id', 'name', 'decants_count', 'ml_per_decant', 'price', 'validity_days', 'max_price_per_ml', 'is_active'])]
#[UseFactory(DecantPackPlanFactory::class)]
final class DecantPackPlanModel extends Model
{
    /** @use HasFactory<DecantPackPlanFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'decants_count' => 'integer',
            'ml_per_decant' => 'integer',
            'price' => 'integer',
            'validity_days' => 'integer',
            'max_price_per_ml' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}

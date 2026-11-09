<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $business_id
 * @property string $customer_id
 * @property string $decant_pack_plan_id
 * @property int $total_decants
 * @property int $remaining_decants
 * @property int $ml_per_decant
 * @property int|null $max_price_per_ml
 * @property string $status
 * @property CarbonImmutable $expires_at
 * @property string|null $idempotency_key
 */
#[Table('decant_packs')]
#[Fillable(['id', 'business_id', 'customer_id', 'decant_pack_plan_id', 'total_decants', 'remaining_decants', 'ml_per_decant', 'max_price_per_ml', 'status', 'expires_at', 'idempotency_key'])]
final class DecantPackModel extends Model
{
    use HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'total_decants' => 'integer',
            'remaining_decants' => 'integer',
            'ml_per_decant' => 'integer',
            'max_price_per_ml' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }
}

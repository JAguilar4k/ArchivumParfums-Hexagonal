<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Infrastructure\Persistence\Models;

use Database\Factories\DecantBottleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $business_id
 * @property string $perfume_variant_id
 * @property int $total_ml
 * @property int $remaining_ml
 * @property int $reserved_ml
 * @property string $status
 * @property Carbon $opened_at
 * @property string|null $notes
 */
#[Table('decant_bottles')]
#[Fillable(['id', 'business_id', 'perfume_variant_id', 'total_ml', 'remaining_ml', 'reserved_ml', 'status', 'opened_at', 'notes'])]
#[UseFactory(DecantBottleFactory::class)]
final class DecantBottleModel extends Model
{
    /** @use HasFactory<DecantBottleFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['total_ml' => 'integer', 'remaining_ml' => 'integer', 'reserved_ml' => 'integer', 'opened_at' => 'datetime'];
    }
}

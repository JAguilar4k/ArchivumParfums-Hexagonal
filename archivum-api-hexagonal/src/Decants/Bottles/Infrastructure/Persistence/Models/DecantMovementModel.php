<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Table('decant_movements')]
#[Fillable(['business_id', 'decant_bottle_id', 'type', 'ml', 'order_line_id', 'user_id', 'reason', 'created_at'])]
final class DecantMovementModel extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['ml' => 'integer'];
    }
}

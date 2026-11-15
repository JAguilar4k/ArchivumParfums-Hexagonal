<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A decant of a pack redeemed in a line of an order (R34).
 *
 * @property string $id
 * @property string $business_id
 * @property string $decant_pack_id
 * @property string $order_line_id
 */
#[Table('decant_pack_redemptions')]
#[Fillable(['id', 'business_id', 'decant_pack_id', 'order_line_id'])]
final class DecantPackRedemptionModel extends Model
{
    use HasUuids;
}

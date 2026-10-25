<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $business_id
 * @property string $order_id
 * @property string $kind
 * @property string|null $perfume_variant_id
 * @property string|null $decant_bottle_id
 * @property int|null $ml
 * @property int $quantity
 * @property int $unit_price
 * @property string $status
 * @property string|null $decant_pack_id
 * @property string|null $purchase_order_line_id
 * @property string|null $perfume_name
 * @property int|null $variant_ml
 */
#[Table('order_lines')]
#[Fillable(['id', 'business_id', 'order_id', 'kind', 'perfume_variant_id', 'decant_bottle_id', 'ml', 'quantity', 'unit_price', 'status', 'decant_pack_id', 'purchase_order_line_id'])]
final class OrderLineModel extends Model
{
    use HasUuids;

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['ml' => 'integer', 'quantity' => 'integer', 'unit_price' => 'integer', 'variant_ml' => 'integer'];
    }
}

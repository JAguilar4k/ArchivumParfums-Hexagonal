<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\JoinClause;

/**
 * @property string $id
 * @property string $business_id
 * @property string $customer_id
 * @property string $status
 * @property int $subtotal
 * @property int $due_now
 * @property int $paid
 * @property int $balance
 * @property int $deposit_percent
 * @property string|null $delivery_method
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $customer_name
 * @property string|null $business_name
 * @property-read Collection<int,OrderLineModel> $lines
 */
#[Table('orders')]
#[Fillable(['id', 'business_id', 'customer_id', 'status', 'subtotal', 'due_now', 'paid', 'balance', 'deposit_percent', 'delivery_method', 'expires_at', 'cancelled_at', 'created_at'])]
#[UseFactory(OrderFactory::class)]
final class OrderModel extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, HasUuids;

    /**
     * Load the current catalogue labels together with all lines in one query.
     *
     * @return HasMany<OrderLineModel,$this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLineModel::class, 'order_id')
            ->leftJoin('perfume_variants as variants', function (JoinClause $join): void {
                $join->on('variants.id', '=', 'order_lines.perfume_variant_id')->on('variants.business_id', '=', 'order_lines.business_id');
            })
            ->leftJoin('perfumes', function (JoinClause $join): void {
                $join->on('perfumes.id', '=', 'variants.perfume_id')->on('perfumes.business_id', '=', 'order_lines.business_id');
            })
            ->select(['order_lines.*', 'perfumes.name as perfume_name', 'variants.size_ml as variant_ml'])
            ->orderBy('order_lines.id');
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['subtotal' => 'integer', 'due_now' => 'integer', 'paid' => 'integer', 'balance' => 'integer', 'deposit_percent' => 'integer', 'created_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }
}

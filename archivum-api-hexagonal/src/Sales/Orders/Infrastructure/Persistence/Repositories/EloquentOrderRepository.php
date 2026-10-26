<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Infrastructure\Persistence\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Sales\Orders\Application\Contracts\OrderSearch;
use Src\Sales\Orders\Domain\Contracts\OrderRepository;
use Src\Sales\Orders\Domain\Entities\Order;
use Src\Sales\Orders\Domain\Entities\OrderLine;
use Src\Sales\Orders\Domain\Exceptions\OrderConflictException;
use Src\Sales\Orders\Domain\ValueObjects\OrderAmounts;
use Src\Sales\Orders\Domain\ValueObjects\OrderId;
use Src\Sales\Orders\Domain\ValueObjects\OrderLineKind;
use Src\Sales\Orders\Domain\ValueObjects\OrderLineStatus;
use Src\Sales\Orders\Domain\ValueObjects\OrderStatus;
use Src\Sales\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Sales\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class EloquentOrderRepository implements OrderRepository, OrderSearch
{
    public function __construct(private BusinessContext $business) {}

    public function nextId(): OrderId
    {
        return new OrderId((string) Str::uuid7());
    }

    public function nextLineId(): string
    {
        return (string) Str::uuid7();
    }

    public function find(OrderId $id, bool $forUpdate = false): ?Order
    {
        $query = $this->query()->where('orders.id', $id->value);

        if ($forUpdate) {
            $query->lock('for update of orders');
        }

        $model = $query->first();

        return $model === null ? null : $this->toDomain($model);
    }

    /** @return list<Order> */
    public function all(?string $userId = null, ?OrderStatus $status = null): array
    {
        $query = $this->query()->orderByDesc('orders.created_at')->orderByDesc('orders.id');

        if ($userId !== null) {
            $query->where('customers.user_id', $userId);
        }

        if ($status !== null) {
            $query->where('orders.status', $status->value);
        }

        return array_values($query->get()->map(fn (OrderModel $model): Order => $this->toDomain($model))->all());
    }

    /** Store the aggregate inside a savepoint, translating integrity failures after rollback. */
    public function save(Order $order): void
    {
        if ($order->businessId->value !== $this->business->current()->value) {
            throw new OrderConflictException('orders.invalid_business');
        }

        try {
            DB::transaction(function () use ($order): void {
                OrderModel::query()->updateOrCreate(['id' => $order->id->value, 'business_id' => $order->businessId->value], [
                    'customer_id' => $order->customerId, 'status' => $order->status->value,
                    'subtotal' => $order->amounts->subtotal, 'due_now' => $order->amounts->dueNow, 'paid' => $order->amounts->paid, 'balance' => $order->amounts->balance,
                    'deposit_percent' => $order->depositPercent, 'delivery_method' => $order->deliveryMethod, 'expires_at' => $order->expiresAt, 'cancelled_at' => $order->cancelledAt, 'created_at' => $order->createdAt,
                ]);

                foreach ($order->lines as $line) {
                    OrderLineModel::query()->updateOrCreate(['id' => $line->id, 'business_id' => $order->businessId->value], [
                        'order_id' => $order->id->value, 'kind' => $line->kind->value, 'perfume_variant_id' => $line->perfumeVariantId, 'decant_bottle_id' => $line->decantBottleId,
                        'ml' => $line->ml, 'quantity' => $line->quantity, 'unit_price' => $line->unitPrice->amount, 'status' => $line->status->value, 'decant_pack_id' => $line->decantPackId, 'purchase_order_line_id' => $line->purchaseOrderLineId,
                    ]);
                }
            });
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23514', '23503'], true)) {
                throw new OrderConflictException('orders.invalid_line');
            }

            throw $exception;
        }
    }

    /** @return Builder<OrderModel> */
    private function query(): Builder
    {
        $businessId = $this->business->current()->value;

        return OrderModel::query()->with(['lines' => fn ($query) => $query->where('order_lines.business_id', $businessId)])
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->join('users', 'users.id', '=', 'customers.user_id')
            ->join('businesses', 'businesses.id', '=', 'orders.business_id')
            ->whereColumn('customers.business_id', 'orders.business_id')
            ->where('orders.business_id', $this->business->current()->value)
            ->select(['orders.*', 'users.name as customer_name', 'businesses.name as business_name']);
    }

    private function toDomain(OrderModel $model): Order
    {
        $lines = array_values($model->lines->map(fn (OrderLineModel $line): OrderLine => OrderLine::reconstitute(
            $line->id, OrderLineKind::from($line->kind), $line->perfume_variant_id, $line->decant_bottle_id, $line->ml, $line->quantity, $line->unit_price,
            OrderLineStatus::from($line->status), $line->decant_pack_id, $line->purchase_order_line_id, $line->perfume_name, $line->variant_ml,
        ))->all());

        return Order::reconstitute(new OrderId($model->id), new BusinessId($model->business_id), $model->customer_id, $lines,
            OrderStatus::from($model->status), new OrderAmounts($model->subtotal, $model->due_now, $model->paid, $model->balance), $model->created_at, $model->expires_at, $model->cancelled_at, $model->delivery_method, $model->deposit_percent, $model->customer_name, $model->business_name);
    }
}

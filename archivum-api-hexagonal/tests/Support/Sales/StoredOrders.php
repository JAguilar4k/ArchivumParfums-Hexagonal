<?php

declare(strict_types=1);

namespace Tests\Support\Sales;

use Illuminate\Support\Str;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Decants\Bottles\Infrastructure\Persistence\Models\DecantBottleModel;
use Src\Sales\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Sales\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Catalog\StoredPerfumes;

final class StoredOrders
{
    /**
     * Store a fictitious order independently of the HTTP creation flow being tested.
     *
     * @return array{order:OrderModel,bottle:?DecantBottleModel,variant_id:string}
     */
    public static function create(StaffedBusiness $business, string $status = 'pending_payment', bool $withBottle = true, bool $withDecant = false): array
    {
        $businessId = $business->business->id;

        return app(BusinessTransaction::class)->run($businessId, function () use ($business, $businessId, $status, $withBottle, $withDecant): array {
            $perfume = StoredPerfumes::create($businessId, variants: [[100, 15000, 15000]]);
            $variantId = $perfume->variants[0]->id->value;
            $customer = CustomerModel::query()->where('business_id', $businessId)->where('user_id', $business->customer->id)->firstOrFail();
            $subtotal = ($withBottle ? 15000 : 0) + ($withDecant ? 800 : 0);
            $due = ($withBottle ? 7500 : 0) + ($withDecant ? 800 : 0);
            $paid = $status === 'pending_payment' ? 0 : $due;
            $order = OrderModel::factory()->create(['business_id' => $businessId, 'customer_id' => $customer->id, 'status' => $status, 'subtotal' => $subtotal, 'due_now' => $due, 'paid' => $paid, 'balance' => $withBottle ? 7500 : 0]);
            $lines = [];

            if ($withBottle) {
                $lines[] = ['kind' => 'bottle', 'perfume_variant_id' => $variantId, 'quantity' => 1, 'unit_price' => 15000,
                    'status' => in_array($status, ['ordered', 'ready', 'delivered'], true) ? ($status === 'ordered' ? 'ordered' : 'received') : 'pending'];
            }

            $bottle = null;

            if ($withDecant) {
                $prepared = in_array($status, ['ready', 'delivered'], true);
                $bottle = DecantBottleModel::factory()->forPresentation($businessId, $variantId, 100)->create(['reserved_ml' => $prepared ? 0 : 5, 'remaining_ml' => $prepared ? 95 : 100]);
                $lines[] = ['kind' => 'decant', 'perfume_variant_id' => $variantId, 'decant_bottle_id' => $bottle->id, 'ml' => 5, 'quantity' => 1, 'unit_price' => 800, 'status' => $prepared ? 'prepared' : 'reserved'];
            }

            foreach ($lines as $line) {
                OrderLineModel::query()->create(['id' => (string) Str::uuid7(), 'business_id' => $businessId, 'order_id' => $order->id, ...$line]);
            }

            return ['order' => $order, 'bottle' => $bottle, 'variant_id' => $variantId];
        });
    }
}

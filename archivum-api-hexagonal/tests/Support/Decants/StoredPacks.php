<?php

declare(strict_types=1);

namespace Tests\Support\Decants;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;

final class StoredPacks
{
    /**
     * Store an offer of 5 decants of 5 ml for 180 days and a pack of the customer of the business that waits for its
     * payment, independently of the purchase flow, in the transaction of that business.
     *
     * @param  array<string, mixed>  $pack  columns of the pack that change the default ones
     * @return array{plan_id: string, pack_id: string, customer_id: string}
     */
    public static function create(StaffedBusiness $business, array $pack = []): array
    {
        $businessId = $business->business->id;

        return app(BusinessTransaction::class)->run($businessId, function () use ($business, $businessId, $pack): array {
            $customerId = (string) CustomerModel::query()->where('user_id', $business->customer->id)->value('id');
            $planId = (string) Str::uuid7();
            $packId = (string) Str::uuid7();
            DB::table('decant_pack_plans')->insert([
                'id' => $planId, 'business_id' => $businessId, 'name' => 'Descubrimiento', 'decants_count' => 5, 'ml_per_decant' => 5,
                'price' => 40000, 'validity_days' => 180, 'max_price_per_ml' => 2500, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('decant_packs')->insert([
                'id' => $packId, 'business_id' => $businessId, 'customer_id' => $customerId, 'decant_pack_plan_id' => $planId,
                'total_decants' => 5, 'remaining_decants' => 5, 'ml_per_decant' => 5, 'max_price_per_ml' => 2500,
                'status' => 'pending_payment', 'expires_at' => now()->addMinutes(30), 'created_at' => now(), 'updated_at' => now(), ...$pack,
            ]);

            return ['plan_id' => $planId, 'pack_id' => $packId, 'customer_id' => $customerId];
        });
    }
}

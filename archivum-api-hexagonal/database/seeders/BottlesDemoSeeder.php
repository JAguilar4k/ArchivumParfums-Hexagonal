<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

final class BottlesDemoSeeder extends Seeder
{
    /** Seed one fictitious open bottle per presentation, without overwriting stock when the seeder runs again. */
    public function run(): void
    {
        foreach ([BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID, BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID] as $businessId) {
            new BusinessTransaction()->run($businessId, function () use ($businessId): void {
                $variants = DB::table('perfume_variants')->where('business_id', $businessId)->where('is_active', true)->whereNotNull('sale_price')->orderBy('id')->get(['id', 'size_ml']);

                foreach ($variants as $variant) {
                    if (DB::table('decant_bottles')->where('business_id', $businessId)->where('perfume_variant_id', $variant->id)->exists()) {
                        continue;
                    }

                    DB::table('decant_bottles')->insert([
                        'business_id' => $businessId,
                        'perfume_variant_id' => $variant->id,
                        'total_ml' => $variant->size_ml,
                        'remaining_ml' => $variant->size_ml,
                        'reserved_ml' => 0,
                        'status' => 'open',
                        'opened_at' => '2026-10-24T10:00:00-06:00',
                        'notes' => 'Botella ficticia para la demostración.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        }
    }
}

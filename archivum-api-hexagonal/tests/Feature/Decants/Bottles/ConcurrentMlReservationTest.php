<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Decants\Bottles\Domain\Contracts\MlReservation;
use Src\Decants\Bottles\Domain\Exceptions\InsufficientMlException;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

it('lets exactly one of two parallel processes reserve the last ml outside the test transaction', function () {
    $businessId = (string) Str::uuid7();
    $perfumeId = (string) Str::uuid7();
    $variantId = (string) Str::uuid7();
    $bottleId = (string) Str::uuid7();
    $owner = DB::connection('pgsql_admin');
    $owner->transaction(function () use ($owner, $businessId, $perfumeId, $variantId, $bottleId): void {
        $owner->statement("SELECT set_config('app.business_id', ?, true)", [$businessId]);
        $owner->table('businesses')->insert(['id' => $businessId, 'name' => 'Perfumería paralela', 'slug' => 'parallel-'.$businessId, 'timezone' => 'America/Costa_Rica', 'settings' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        $owner->table('perfumes')->insert(['id' => $perfumeId, 'business_id' => $businessId, 'brand' => 'Marca ficticia', 'name' => 'Perfume paralelo', 'created_at' => now(), 'updated_at' => now()]);
        $owner->table('perfume_variants')->insert(['id' => $variantId, 'business_id' => $businessId, 'perfume_id' => $perfumeId, 'size_ml' => 100, 'sale_price' => 10000, 'created_at' => now(), 'updated_at' => now()]);
        $owner->table('decant_bottles')->insert(['id' => $bottleId, 'business_id' => $businessId, 'perfume_variant_id' => $variantId, 'total_ml' => 100, 'remaining_ml' => 5, 'reserved_ml' => 0, 'status' => 'open', 'opened_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    });

    $results = inParallel(['businesses', 'perfumes', 'perfume_variants', 'decant_bottles', 'decant_movements'],
        static function () use ($businessId, $bottleId): string {
            return new BusinessTransaction()->run($businessId, function () use ($businessId, $bottleId): string {
                app()->instance(BusinessContext::class, new FixedBusinessContext(new BusinessId($businessId)));
                try {
                    app(MlReservation::class)->reserve($bottleId, 5);

                    return 'reserved';
                } catch (InsufficientMlException) {
                    return 'conflict';
                }
            });
        },
        static function () use ($businessId, $bottleId): string {
            return new BusinessTransaction()->run($businessId, function () use ($businessId, $bottleId): string {
                app()->instance(BusinessContext::class, new FixedBusinessContext(new BusinessId($businessId)));
                try {
                    app(MlReservation::class)->reserve($bottleId, 5);

                    return 'reserved';
                } catch (InsufficientMlException) {
                    return 'conflict';
                }
            });
        });

    sort($results);
    expect($results)->toBe(['conflict', 'reserved']);
    $this->scopeQueriesTo($businessId);
    assertDatabaseHas('decant_bottles', ['id' => $bottleId, 'remaining_ml' => 5, 'reserved_ml' => 5]);
    assertDatabaseCount('decant_movements', 1);
});

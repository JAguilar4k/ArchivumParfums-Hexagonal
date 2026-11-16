<?php

declare(strict_types=1);

use Database\Seeders\BusinessesSeeder;
use Database\Seeders\DecantPackPlansDemoSeeder;
use Illuminate\Support\Facades\DB;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Decants\Packs\Infrastructure\Persistence\Models\DecantPackPlanModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\getJson;
use function Pest\Laravel\seed;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

it('R32: seeds the initial offer in both demo businesses through db seed, without repeating it', function () {
    config(['archivum.demo_password' => 'clave-de-demostracion']);
    seed();
    seed(DecantPackPlansDemoSeeder::class);

    foreach ([BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID, BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID] as $businessId) {
        $this->scopeQueriesTo($businessId);
        $slug = BusinessModel::query()->findOrFail($businessId)->slug;

        getJson('/api/v1/tiendas/'.$slug.'/paquetes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Descubrimiento')
            ->assertJsonPath('data.0.cantidad_decants', 5)
            ->assertJsonPath('data.0.ml_por_decant', 5)
            ->assertJsonPath('data.0.precio', 40000)
            ->assertJsonPath('data.0.vigencia_dias', 180)
            ->assertJsonPath('data.0.precio_maximo_por_ml', 2500);

        $this->scopeQueriesTo($businessId);
        expect(DecantPackPlanModel::query()->count())->toBe(1);
    }
});

it('R32: leaves out the most expensive presentations of the demo with the cap of the initial offer', function () {
    config(['archivum.demo_password' => 'clave-de-demostracion']);
    seed();

    foreach ([BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID, BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID] as $businessId) {
        $this->scopeQueriesTo($businessId);
        $cap = (int) DecantPackPlanModel::query()->value('max_price_per_ml');
        $presentations = DB::table('perfume_variants')->where('is_active', true)->whereNotNull('sale_price')->get(['sale_price', 'size_ml']);
        $covered = $presentations->filter(fn (object $variant): bool => $variant->sale_price <= $cap * $variant->size_ml);

        expect($covered)->toHaveCount(3)
            ->and($presentations->count())->toBeGreaterThan(3);
    }
});

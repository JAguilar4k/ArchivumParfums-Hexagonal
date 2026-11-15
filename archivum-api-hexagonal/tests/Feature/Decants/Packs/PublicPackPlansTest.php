<?php

declare(strict_types=1);

use Src\Decants\Packs\Infrastructure\Persistence\Models\DecantPackPlanModel;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\getJson;

pest()->use(RefreshDatabaseAsOwner::class);

beforeEach(function () {
    TwoBusinesses::create();
});

it('R32: lists to any visitor only the offers on sale of the store of the address, by name, with what a visitor needs', function () {
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $summer = DecantPackPlanModel::factory()->create(['business_id' => $first, 'name' => 'Verano', 'decants_count' => 10, 'ml_per_decant' => 2, 'price' => 25000, 'validity_days' => 90, 'max_price_per_ml' => null]);
    $discovery = DecantPackPlanModel::factory()->create(['business_id' => $first]);
    DecantPackPlanModel::factory()->inactive()->create(['business_id' => $first, 'name' => 'Fuera de venta']);
    DecantPackPlanModel::factory()->create(['business_id' => TwoBusinesses::idOf(TwoBusinesses::SECOND), 'name' => 'De la otra tienda']);

    getJson('/api/v1/tiendas/'.TwoBusinesses::FIRST.'/paquetes')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['id' => $discovery->id, 'nombre' => 'Descubrimiento', 'cantidad_decants' => 5, 'ml_por_decant' => 5, 'precio' => 40000, 'vigencia_dias' => 180, 'precio_maximo_por_ml' => 2500],
            ['id' => $summer->id, 'nombre' => 'Verano', 'cantidad_decants' => 10, 'ml_por_decant' => 2, 'precio' => 25000, 'vigencia_dias' => 90, 'precio_maximo_por_ml' => null],
        ]]);
});

it('answers an empty list for a store with no offers on sale', function () {
    getJson('/api/v1/tiendas/'.TwoBusinesses::SECOND.'/paquetes')->assertOk()->assertExactJson(['data' => []]);
});

it('answers 404 no-encontrado for a store that does not exist', function () {
    getJson('/api/v1/tiendas/tienda-que-no-existe/paquetes')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/no-encontrado');
});

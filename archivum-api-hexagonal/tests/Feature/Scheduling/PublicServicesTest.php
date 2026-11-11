<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\getJson;

uses(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

beforeEach(function (): void {
    TwoBusinesses::create();
});

/** Insert a service of the business the queries are scoped to, as the panel would have written it. */
function insertPublicService(
    string $businessId,
    string $name,
    bool $active = true,
    int $duration = 45,
    int $price = 0,
    int $maxPerfumes = 5,
): string {
    $id = (string) Str::uuid7();

    DB::table('services')->insert([
        'id' => $id,
        'business_id' => $businessId,
        'name' => $name,
        'duration_minutes' => $duration,
        'price' => $price,
        'buffer_minutes' => 15,
        'max_perfumes' => $maxPerfumes,
        'is_active' => $active,
    ]);

    return $id;
}

it('lists the active services of the store ordered by name, without a session', function (): void {
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $second = TwoBusinesses::idOf(TwoBusinesses::SECOND);

    $this->scopeQueriesTo($first);
    insertPublicService($first, 'Zeta', duration: 30, price: 25000, maxPerfumes: 3);
    insertPublicService($first, 'Alfa');
    insertPublicService($first, 'Beta', active: false);

    $this->scopeQueriesTo($second);
    insertPublicService($second, 'Servicio de la otra tienda');

    $response = getJson('/api/v1/tiendas/'.TwoBusinesses::FIRST.'/servicios');

    $response->assertOk();

    $services = $response->json('data');

    expect(array_column($services, 'nombre'))->toBe(['Alfa', 'Zeta'])
        // The resource is a whitelist: the margin and any internal field never leave the API.
        ->and(array_keys($services[0]))->toBe(['id', 'nombre', 'duracion_minutos', 'precio', 'maximo_perfumes'])
        ->and($services[1])->toMatchArray([
            'nombre' => 'Zeta',
            'duracion_minutos' => 30,
            'precio' => 25000,
            'maximo_perfumes' => 3,
        ]);
});

it('answers each store only with its own services, and none of the other', function (): void {
    foreach ([TwoBusinesses::FIRST => TwoBusinesses::SECOND, TwoBusinesses::SECOND => TwoBusinesses::FIRST] as $own => $other) {
        $this->scopeQueriesTo(TwoBusinesses::idOf($other));
        $foreign = insertPublicService(TwoBusinesses::idOf($other), 'Servicio de la otra tienda');

        $this->scopeQueriesTo(TwoBusinesses::idOf($own));
        $ownService = insertPublicService(TwoBusinesses::idOf($own), 'Prueba de olores');

        $ids = array_column(getJson('/api/v1/tiendas/'.$own.'/servicios')->assertOk()->json('data'), 'id');

        expect($ids)->toContain($ownService)
            ->and($ids)->not->toContain($foreign);
    }
});

it('answers 404 no-encontrado for a store that does not exist', function (): void {
    $response = getJson('/api/v1/tiendas/tienda-inexistente/servicios');

    $response->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json');

    expect($response->json('type'))->toEndWith('/problemas/no-encontrado');
});

<?php

declare(strict_types=1);

use Database\Seeders\BusinessesSeeder;
use Database\Seeders\CatalogDemoSeeder;
use Tests\Support\Catalog\ServesCatalog;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\getJson;
use function Pest\Laravel\seed;

pest()->use(RefreshDatabaseAsOwner::class);

beforeEach(function () {
    seed(BusinessesSeeder::class);
    seed(CatalogDemoSeeder::class);
    ServesCatalog::register();
});

it('shows in the showcase of each demo store its own sample perfumes and none of the other', function () {
    $pilot = getJson('/api/v1/tiendas/archivumparfums/perfumes')->assertOk();
    $second = getJson('/api/v1/tiendas/esencias-del-norte/perfumes')->assertOk();

    $pilotNames = array_column($pilot->json('data'), 'nombre');
    $secondNames = array_column($second->json('data'), 'nombre');

    expect($pilotNames)->toHaveCount(4)->toContain('Oud Nocturno', 'Alba de Azahar', 'Sal Marina', 'Tabaco Dulce')
        ->and($secondNames)->toHaveCount(3)->toContain('Cedro y Bruma', 'Café y Vainilla', 'Jardín de Orquídeas')
        ->and(array_intersect($pilotNames, $secondNames))->toBe([]);
});

it('shows every sample perfume with presentations that have a price in whole colones', function (string $slug) {
    foreach (getJson("/api/v1/tiendas/{$slug}/perfumes")->assertOk()->json('data') as $perfume) {
        expect($perfume['presentaciones'])->not->toBe([]);

        foreach ($perfume['presentaciones'] as $presentation) {
            expect($presentation['precio_venta'])->toBeInt()->toBeGreaterThan(0)
                ->and($presentation['ml'])->toBeInt();
        }
    }
})->with(['archivumparfums', 'esencias-del-norte']);

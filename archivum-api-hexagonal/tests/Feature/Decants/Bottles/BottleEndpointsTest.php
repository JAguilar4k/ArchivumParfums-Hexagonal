<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Src\Decants\Bottles\Infrastructure\Persistence\Models\DecantBottleModel;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Catalog\StoredPerfumes;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

it('registers server capacity and configurable initial remaining ml with 201 Location', function () {
    $business = actingAsOwner();
    $perfume = StoredPerfumes::create($business->business->id, variants: [[100, 10000, 10000]]);

    $response = postJson('/api/v1/panel/botellas', ['presentacion_id' => $perfume->variants[0]->id->value, 'ml_restantes' => 75, 'abierta_en' => '2026-10-24T10:00:00-06:00', 'business_id' => (string) Str::uuid7()]);

    $response->assertCreated()->assertHeader('Location')->assertJsonPath('data.ml_totales', 100)->assertJsonPath('data.ml_restantes', 75)->assertJsonPath('data.ml_reservados', 0)->assertJsonPath('data.estado', 'abierta')->assertJsonMissingPath('data.business_id');
    $this->scopeQueriesTo($business->business->id);
    assertDatabaseHas('decant_bottles', ['id' => $response->json('data.id'), 'business_id' => $business->business->id, 'remaining_ml' => 75]);
});

it('defaults remaining ml to the capacity', function () {
    $business = actingAsOwner();
    $perfume = StoredPerfumes::create($business->business->id, variants: [[75, 85000, 85000]]);

    postJson('/api/v1/panel/botellas', ['presentacion_id' => $perfume->variants[0]->id->value, 'ml_totales' => 75, 'abierta_en' => '2026-10-24'])->assertCreated()->assertJsonPath('data.ml_restantes', 75);
});

it('returns 422 without a bottle on invalid input', function (array $changes, string $field) {
    $business = actingAsOwner();
    $perfume = StoredPerfumes::create($business->business->id, variants: [[100, 10000, 10000]]);

    postJson('/api/v1/panel/botellas', [...['presentacion_id' => $perfume->variants[0]->id->value, 'abierta_en' => '2026-10-24'], ...$changes])->assertUnprocessable()->assertJsonStructure(['errores' => [$field]]);
    $this->scopeQueriesTo($business->business->id);
    assertDatabaseCount('decant_bottles', 0);
})->with([[['presentacion_id' => 'invalid'], 'presentacion_id'], [['presentacion_id' => '0192f0c4-0000-7000-9000-000000000099'], 'presentacion_id'], [['ml_totales' => 90], 'ml_totales'], [['ml_restantes' => 101], 'ml_restantes'], [['ml_restantes' => -1], 'ml_restantes'], [['ml_restantes' => 0.5], 'ml_restantes'], [['abierta_en' => 'invalid'], 'abierta_en']]);

it('lists only the session business and patches notes while ignoring stock and business', function () {
    $business = actingAsOwner();
    $bottle = DecantBottleModel::factory()->create(['business_id' => $business->business->id]);
    DecantBottleModel::factory()->create();

    getJson('/api/v1/panel/botellas?por_pagina=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $bottle->id)->assertJsonPath('meta.total', 1);
    patchJson('/api/v1/panel/botellas/'.$bottle->id, ['notas' => 'Uso en citas', 'ml_restantes' => 0, 'ml_reservados' => 99, 'estado' => 'vacia', 'business_id' => (string) Str::uuid7()])->assertOk()->assertJsonPath('data.notas', 'Uso en citas')->assertJsonPath('data.ml_restantes', 100)->assertJsonPath('data.estado', 'abierta');
    $this->scopeQueriesTo($business->business->id);
    assertDatabaseHas('decant_bottles', ['id' => $bottle->id, 'remaining_ml' => 100, 'reserved_ml' => 0, 'notes' => 'Uso en citas']);
});

it('returns 403 on every operation for staff and customers', function (string $role) {
    $business = StaffedBusiness::create();
    $bottle = DecantBottleModel::factory()->create(['business_id' => $business->business->id]);
    actingAsRole($role, $business);
    $url = '/api/v1/panel/botellas/'.$bottle->id;

    getJson('/api/v1/panel/botellas')->assertForbidden();
    postJson('/api/v1/panel/botellas', [])->assertForbidden();
    getJson($url)->assertForbidden();
    patchJson($url, ['notas' => 'Intrusión'])->assertForbidden();
    getJson($url.'/movimientos')->assertForbidden();
    postJson($url.'/ajustes', ['ml' => -1, 'motivo' => 'Intrusión'])->assertForbidden();
})->with(['colaborador', 'cliente']);

it('returns 404 for foreign bottles and 422 for foreign presentations', function () {
    $business = actingAsOwner();
    $foreign = DecantBottleModel::factory()->create();
    $url = '/api/v1/panel/botellas/'.$foreign->id;

    getJson($url)->assertNotFound();
    patchJson($url, ['notas' => 'Intrusión'])->assertNotFound();
    getJson($url.'/movimientos')->assertNotFound();
    postJson($url.'/ajustes', ['ml' => -1, 'motivo' => 'Intrusión'])->assertNotFound();
    postJson('/api/v1/panel/botellas', ['presentacion_id' => $foreign->perfume_variant_id, 'abierta_en' => '2026-10-24'])->assertUnprocessable()->assertJsonStructure(['errores' => ['presentacion_id']]);
    $this->scopeQueriesTo($business->business->id);
    assertDatabaseCount('decant_bottles', 0);
});

it('records a signed manual adjustment with its author and updated bottle', function () {
    $business = actingAsOwner();
    $bottle = DecantBottleModel::factory()->create(['business_id' => $business->business->id]);
    $url = '/api/v1/panel/botellas/'.$bottle->id;

    postJson($url.'/ajustes', ['ml' => -20, 'motivo' => 'Conteo físico'])->assertCreated()->assertJsonPath('data.botella.ml_restantes', 80)->assertJsonPath('data.movimiento.tipo', 'ajuste')->assertJsonPath('data.movimiento.ml', -20)->assertJsonPath('data.movimiento.hecho_por', $business->owner->name);
    getJson($url.'/movimientos')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.motivo', 'Conteo físico');
    $this->scopeQueriesTo($business->business->id);
    assertDatabaseHas('decant_movements', ['decant_bottle_id' => $bottle->id, 'type' => 'adjustment', 'ml' => -20, 'user_id' => $business->owner->id]);
});

it('returns 422 for zero ml or a missing adjustment reason', function (array $payload, string $field) {
    $business = actingAsOwner();
    $bottle = DecantBottleModel::factory()->create(['business_id' => $business->business->id]);

    postJson('/api/v1/panel/botellas/'.$bottle->id.'/ajustes', $payload)->assertUnprocessable()->assertJsonStructure(['errores' => [$field]]);
})->with([[['ml' => 0, 'motivo' => 'Conteo'], 'ml'], [['ml' => -1], 'motivo'], [['ml' => -1, 'motivo' => '   '], 'motivo']]);

it('returns 409 without writing a movement when adjustments exceed stock limits', function (int $remaining, int $reserved, int $ml) {
    $business = actingAsOwner();
    $bottle = DecantBottleModel::factory()->withRemainingMl($remaining)->create(['business_id' => $business->business->id, 'reserved_ml' => $reserved]);

    postJson('/api/v1/panel/botellas/'.$bottle->id.'/ajustes', ['ml' => $ml, 'motivo' => 'Conteo'])->assertConflict()->assertJsonPath('detail', __('bottles.insufficient_ml'));
    $this->scopeQueriesTo($business->business->id);
    assertDatabaseHas('decant_bottles', ['id' => $bottle->id, 'remaining_ml' => $remaining, 'reserved_ml' => $reserved]);
    assertDatabaseCount('decant_movements', 0);
})->with([[100, 0, 1], [5, 0, -6], [10, 5, -6], [0, 0, 1]]);

it('becomes empty when an adjustment removes the final ml', function () {
    $business = actingAsOwner();
    $bottle = DecantBottleModel::factory()->withRemainingMl(5)->create(['business_id' => $business->business->id]);

    postJson('/api/v1/panel/botellas/'.$bottle->id.'/ajustes', ['ml' => -5, 'motivo' => 'Botella terminada'])->assertCreated()->assertJsonPath('data.botella.estado', 'vacia')->assertJsonPath('data.botella.ml_restantes', 0);
});

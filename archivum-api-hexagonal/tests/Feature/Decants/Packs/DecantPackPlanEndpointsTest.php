<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Decants\Packs\Infrastructure\Persistence\Models\DecantPackPlanModel;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\Decants\StoredPacks;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

const PACK_PLANS_ENDPOINT = '/api/v1/panel/paquetes';

/**
 * The initial offer of the specification, with the parts that a test changes given by name.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function packPlanPayload(array $overrides = []): array
{
    return [
        'nombre' => 'Descubrimiento',
        'cantidad_decants' => 5,
        'ml_por_decant' => 5,
        'precio' => 40000,
        'vigencia_dias' => 180,
        'precio_maximo_por_ml' => 2500,
        ...$overrides,
    ];
}

/**
 * Field errors of a 422 problem, by field name.
 *
 * @param  TestResponse<JsonResponse>  $response
 * @return array<string, list<string>>
 */
function packPlanErrorsOf(TestResponse $response): array
{
    $response->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/datos-invalidos');

    return $response->json('errores');
}

/**
 * Define an offer through the API as the owner of the session and answer its identifier.
 *
 * @param  array<string, mixed>  $overrides
 */
function definedPackPlan(array $overrides = []): string
{
    return (string) postJson(PACK_PLANS_ENDPOINT, packPlanPayload($overrides))->assertCreated()->json('data.id');
}

beforeEach(function () {
    TwoBusinesses::create();
    TwoBusinesses::actAsOwnerOf(TwoBusinesses::FIRST);
});

it('R32: defines the offer and answers 201 with its Location and only the fields of the resource', function () {
    $response = postJson(PACK_PLANS_ENDPOINT, packPlanPayload())->assertCreated();

    $id = $response->json('data.id');
    $response->assertHeader('Location', url(PACK_PLANS_ENDPOINT).'/'.$id)
        ->assertJsonPath('data', [
            'id' => $id, 'nombre' => 'Descubrimiento', 'cantidad_decants' => 5, 'ml_por_decant' => 5, 'precio' => 40000,
            'vigencia_dias' => 180, 'precio_maximo_por_ml' => 2500, 'activo' => true,
        ]);

    $this->scopeQueriesTo(TwoBusinesses::idOf(TwoBusinesses::FIRST));
    assertDatabaseHas('decant_pack_plans', [
        'id' => $id, 'business_id' => TwoBusinesses::idOf(TwoBusinesses::FIRST), 'name' => 'Descubrimiento', 'decants_count' => 5,
        'ml_per_decant' => 5, 'price' => 40000, 'validity_days' => 180, 'max_price_per_ml' => 2500, 'is_active' => true,
    ]);
});

it('R32: defines an offer without a cap, and off sale when the owner asks for it', function () {
    postJson(PACK_PLANS_ENDPOINT, packPlanPayload(['precio_maximo_por_ml' => null, 'activo' => false]))
        ->assertCreated()
        ->assertJsonPath('data.precio_maximo_por_ml', null)
        ->assertJsonPath('data.activo', false);

    $payload = packPlanPayload();
    unset($payload['precio_maximo_por_ml']);

    postJson(PACK_PLANS_ENDPOINT, $payload)->assertCreated()->assertJsonPath('data.precio_maximo_por_ml', null)->assertJsonPath('data.activo', true);
});

it('R32: answers 422 datos-invalidos with the detail of each field in Spanish and stores nothing', function (array $payload, string $field, string $message) {
    $errors = packPlanErrorsOf(postJson(PACK_PLANS_ENDPOINT, $payload));

    expect($errors)->toHaveKey($field)
        ->and($errors[$field])->toContain($message);

    $this->scopeQueriesTo(TwoBusinesses::idOf(TwoBusinesses::FIRST));
    assertDatabaseCount('decant_pack_plans', 0);
})->with([
    'no name' => [packPlanPayload(['nombre' => null]), 'nombre', 'El campo nombre es obligatorio.'],
    'a name too long' => [packPlanPayload(['nombre' => str_repeat('a', 121)]), 'nombre', 'El campo nombre no debe tener más de 120 caracteres.'],
    'no decants' => [packPlanPayload(['cantidad_decants' => 0]), 'cantidad_decants', 'El campo cantidad de decants debe ser al menos 1.'],
    'more than 50 decants' => [packPlanPayload(['cantidad_decants' => 51]), 'cantidad_decants', 'El campo cantidad de decants no debe ser mayor que 50.'],
    'ml in words' => [packPlanPayload(['ml_por_decant' => 'cinco']), 'ml_por_decant', 'El campo ml por decant debe ser un número entero.'],
    'a free pack' => [packPlanPayload(['precio' => 0]), 'precio', 'El campo precio debe ser al menos 1.'],
    'a price with decimals' => [packPlanPayload(['precio' => 40000.5]), 'precio', 'El campo precio debe ser un número entero.'],
    'more than 365 days' => [packPlanPayload(['vigencia_dias' => 366]), 'vigencia_dias', 'El campo vigencia en días no debe ser mayor que 365.'],
    'a cap of nothing' => [packPlanPayload(['precio_maximo_por_ml' => 0]), 'precio_maximo_por_ml', 'El campo precio máximo por ml debe ser al menos 1.'],
    'a state that is not a boolean' => [packPlanPayload(['activo' => 'tal vez']), 'activo', 'El campo activo debe ser verdadero o falso.'],
]);

it('R4: answers 422 in ml_por_decant when the store does not sell decants of those ml, and accepts its own sizes', function () {
    $errors = packPlanErrorsOf(postJson(PACK_PLANS_ENDPOINT, packPlanPayload(['ml_por_decant' => 7])));

    expect($errors)->toBe(['ml_por_decant' => ['Los ml por decant deben ser uno de los tamaños de decant de la tienda: 2, 5, 10 ml.']]);

    patchJson('/api/v1/panel/tienda', ['ajustes' => ['tamanos_decant_ml' => [7, 3]]])->assertOk();

    postJson(PACK_PLANS_ENDPOINT, packPlanPayload(['ml_por_decant' => 7]))->assertCreated()->assertJsonPath('data.ml_por_decant', 7);
    expect(packPlanErrorsOf(postJson(PACK_PLANS_ENDPOINT, packPlanPayload(['ml_por_decant' => 5]))))
        ->toBe(['ml_por_decant' => ['Los ml por decant deben ser uno de los tamaños de decant de la tienda: 3, 7 ml.']]);
});

it('R32: changes only the fields sent and answers 200 with the offer', function () {
    $id = definedPackPlan();

    patchJson(PACK_PLANS_ENDPOINT.'/'.$id, ['precio' => 45000, 'vigencia_dias' => 120])
        ->assertOk()
        ->assertJsonPath('data', [
            'id' => $id, 'nombre' => 'Descubrimiento', 'cantidad_decants' => 5, 'ml_por_decant' => 5, 'precio' => 45000,
            'vigencia_dias' => 120, 'precio_maximo_por_ml' => 2500, 'activo' => true,
        ]);

    patchJson(PACK_PLANS_ENDPOINT.'/'.$id, ['precio_maximo_por_ml' => null])->assertOk()->assertJsonPath('data.precio_maximo_por_ml', null);
    expect(packPlanErrorsOf(patchJson(PACK_PLANS_ENDPOINT.'/'.$id, ['cantidad_decants' => 51])))->toHaveKey('cantidad_decants');
});

it('R32: takes an offer off sale without deleting it, keeps it in the panel and hides it from the store', function () {
    $id = definedPackPlan();

    patchJson(PACK_PLANS_ENDPOINT.'/'.$id, ['activo' => false])->assertOk()->assertJsonPath('data.activo', false);

    getJson(PACK_PLANS_ENDPOINT)->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.activo', false);
    getJson('/api/v1/tiendas/'.TwoBusinesses::FIRST.'/paquetes')->assertOk()->assertJsonCount(0, 'data');

    patchJson(PACK_PLANS_ENDPOINT.'/'.$id, ['activo' => true])->assertOk()->assertJsonPath('data.activo', true);
    getJson('/api/v1/tiendas/'.TwoBusinesses::FIRST.'/paquetes')->assertOk()->assertJsonPath('data.0.id', $id);
});

it('R4: keeps the ml of an offer whose size the store stopped selling, and asks for a size it sells to change them', function () {
    $id = definedPackPlan();
    patchJson('/api/v1/panel/tienda', ['ajustes' => ['tamanos_decant_ml' => [2, 10]]])->assertOk();

    patchJson(PACK_PLANS_ENDPOINT.'/'.$id, ['precio' => 42000])->assertOk()->assertJsonPath('data.ml_por_decant', 5);
    patchJson(PACK_PLANS_ENDPOINT.'/'.$id, ['ml_por_decant' => 5])->assertOk()->assertJsonPath('data.ml_por_decant', 5);
    expect(packPlanErrorsOf(patchJson(PACK_PLANS_ENDPOINT.'/'.$id, ['ml_por_decant' => 7])))->toHaveKey('ml_por_decant');
    patchJson(PACK_PLANS_ENDPOINT.'/'.$id, ['ml_por_decant' => 10])->assertOk()->assertJsonPath('data.ml_por_decant', 10);
});

it('R32: lists the offers of the store of the session, on sale or not, by name', function () {
    $summer = definedPackPlan(['nombre' => 'Verano', 'activo' => false]);
    $discovery = definedPackPlan(['nombre' => 'Descubrimiento']);
    TwoBusinesses::actAsOwnerOf(TwoBusinesses::SECOND);
    definedPackPlan(['nombre' => 'De la otra tienda']);
    TwoBusinesses::actAsOwnerOf(TwoBusinesses::FIRST);

    $response = getJson(PACK_PLANS_ENDPOINT)->assertOk();

    expect($response->json('data.*.id'))->toBe([$discovery, $summer])
        ->and($response->json('data.*.activo'))->toBe([true, false]);
});

it('editing an offer does not change the decants, the ml or the cap of the packs already bought', function () {
    $business = StaffedBusiness::create();
    $stored = StoredPacks::create($business);
    Sanctum::actingAs($business->owner, Ability::namesGrantedTo(AccountRole::Owner));

    patchJson(PACK_PLANS_ENDPOINT.'/'.$stored['plan_id'], ['cantidad_decants' => 10, 'ml_por_decant' => 10, 'precio_maximo_por_ml' => 3000])
        ->assertOk()
        ->assertJsonPath('data.cantidad_decants', 10)
        ->assertJsonPath('data.ml_por_decant', 10);

    $this->scopeQueriesTo($business->business->id);
    expect((array) DB::table('decant_packs')->where('id', $stored['pack_id'])->first(['total_decants', 'remaining_decants', 'ml_per_decant', 'max_price_per_ml']))
        ->toBe(['total_decants' => 5, 'remaining_decants' => 5, 'ml_per_decant' => 5, 'max_price_per_ml' => 2500])
        ->and(DecantPackPlanModel::query()->whereKey($stored['plan_id'])->value('max_price_per_ml'))->toBe(3000);
});

<?php

declare(strict_types=1);

use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Decants\Bottles\Infrastructure\Persistence\Models\DecantBottleModel;
use Src\Decants\Packs\Infrastructure\Persistence\Models\DecantPackModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\Catalog\StoredPerfumes;
use Tests\Support\Decants\StoredPacks;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

const PURCHASED_PACKS_ENDPOINT = '/api/v1/cuenta/paquetes-comprados';

const SOLD_PACKS_ENDPOINT = '/api/v1/panel/paquetes-vendidos';

/** The moment of the requests whose expiries the tests write by hand. */
const PACK_LISTINGS_AT = '2026-11-20T10:00:00-06:00';

/**
 * Store a pack that the account of an email bought in one of the two businesses, from an offer of that business: by
 * default an active pack of 5 decants of 5 ml, with the columns given changed.
 *
 * @param  array<string, mixed>  $columns
 */
function packBoughtIn(string $slug, string $email, array $columns = []): DecantPackModel
{
    $businessId = TwoBusinesses::idOf($slug);
    app(BusinessTransaction::class)->scope($businessId);
    $customerId = CustomerModel::query()->where('business_id', $businessId)->where('user_id', TwoBusinesses::userIdOf($email))->sole()->id;

    return DecantPackModel::factory()->create(['business_id' => $businessId, 'customer_id' => $customerId, ...$columns]);
}

it('R35: lists the packs the customer bought in every store, newest first, with only the fields of the resource', function () {
    travelTo(new DateTimeImmutable(PACK_LISTINGS_AT));
    TwoBusinesses::create();
    $first = packBoughtIn(TwoBusinesses::FIRST, TwoBusinesses::CUSTOMER_OF_BOTH, [
        'remaining_decants' => 2, 'expires_at' => '2027-05-01T10:00:00-06:00', 'created_at' => '2026-11-01T09:00:00-06:00',
    ]);
    $second = packBoughtIn(TwoBusinesses::SECOND, TwoBusinesses::CUSTOMER_OF_BOTH, ['created_at' => '2026-11-10T09:00:00-06:00']);
    $oldest = packBoughtIn(TwoBusinesses::SECOND, TwoBusinesses::CUSTOMER_OF_BOTH, ['created_at' => '2026-10-01T09:00:00-06:00']);
    TwoBusinesses::actAs(TwoBusinesses::CUSTOMER_OF_BOTH);

    $response = getJson(PURCHASED_PACKS_ENDPOINT)->assertOk();

    expect($response->json('data.*.id'))->toBe([$second->id, $first->id, $oldest->id])
        ->and($response->json('data.1'))->toBe([
            'id' => $first->id,
            'tienda' => ['id' => TwoBusinesses::idOf(TwoBusinesses::FIRST), 'nombre' => 'Perfumería Primera', 'slug' => TwoBusinesses::FIRST],
            'oferta' => ['id' => $first->decant_pack_plan_id, 'nombre' => 'Descubrimiento'],
            'decants_totales' => 5,
            'decants_restantes' => 2,
            'ml_por_decant' => 5,
            'estado' => 'activo',
            'vence_en' => '2027-05-01T10:00:00-06:00',
        ])
        ->and($response->json('data.0.tienda.slug'))->toBe(TwoBusinesses::SECOND);
});

it('lists none of the packs of another customer, in the same store or in another one', function () {
    TwoBusinesses::create();
    $own = packBoughtIn(TwoBusinesses::FIRST, TwoBusinesses::CUSTOMER);
    packBoughtIn(TwoBusinesses::FIRST, TwoBusinesses::CUSTOMER_OF_BOTH);
    packBoughtIn(TwoBusinesses::SECOND, TwoBusinesses::CUSTOMER_OF_BOTH);
    TwoBusinesses::actAs(TwoBusinesses::CUSTOMER);

    expect(getJson(PURCHASED_PACKS_ENDPOINT)->assertOk()->json('data.*.id'))->toBe([$own->id]);
});

it('R35: marks the packs that can no longer be used, and an order that uses them gets a 409', function (string $status, int $remaining, int $minutesLeft, string $word, string $key) {
    $business = actingAsCustomer();
    $perfume = StoredPerfumes::create($business->business->id, variants: [[100, 120000, 120000]]);
    $bottle = DecantBottleModel::factory()->forPresentation($business->business->id, $perfume->variants[0]->id->value, 100)->create();
    $pack = StoredPacks::create($business, ['status' => $status, 'remaining_decants' => $remaining, 'expires_at' => now()->addMinutes($minutesLeft)])['pack_id'];

    getJson(PURCHASED_PACKS_ENDPOINT)->assertOk()->assertJsonPath('data.0.id', $pack)->assertJsonPath('data.0.estado', $word);
    postJson('/api/v1/tiendas/'.$business->business->slug.'/pedidos', ['lineas' => [['botella_id' => $bottle->id, 'ml' => 5, 'paquete_id' => $pack]]])
        ->assertConflict()->assertJsonPath('detail', __($key));
})->with([
    'expired by the queue' => ['expired', 3, -60, 'vencido', 'packs.expired'],
    'active after its validity, before the queue expires it' => ['active', 3, -1, 'vencido', 'packs.expired'],
    'exhausted' => ['exhausted', 0, 1440, 'agotado', 'packs.exhausted'],
]);

it('lists to the owner the packs sold in the store of the session, newest first and by pages, with only the name of the customer', function () {
    travelTo(new DateTimeImmutable(PACK_LISTINGS_AT));
    TwoBusinesses::create();
    $older = packBoughtIn(TwoBusinesses::FIRST, TwoBusinesses::CUSTOMER, [
        'remaining_decants' => 1, 'expires_at' => '2027-05-01T10:00:00-06:00', 'created_at' => '2026-11-01T09:00:00-06:00',
    ]);
    $newer = packBoughtIn(TwoBusinesses::FIRST, TwoBusinesses::CUSTOMER_OF_BOTH, ['created_at' => '2026-11-10T09:00:00-06:00']);
    packBoughtIn(TwoBusinesses::SECOND, TwoBusinesses::CUSTOMER_OF_BOTH, ['created_at' => '2026-11-15T09:00:00-06:00']);
    TwoBusinesses::actAsOwnerOf(TwoBusinesses::FIRST);

    $second = getJson(SOLD_PACKS_ENDPOINT.'?por_pagina=1&pagina=2')->assertOk()
        ->assertJsonPath('meta.total', 2)->assertJsonPath('meta.per_page', 1)->assertJsonPath('meta.current_page', 2);
    parse_str((string) parse_url((string) $second->json('links.prev'), PHP_URL_QUERY), $previous);

    expect($second->json('data'))->toBe([[
        'id' => $older->id,
        'cliente' => ['nombre' => UserModel::query()->where('email', TwoBusinesses::CUSTOMER)->sole()->name],
        'oferta' => ['id' => $older->decant_pack_plan_id, 'nombre' => 'Descubrimiento'],
        'decants_totales' => 5,
        'decants_restantes' => 1,
        'ml_por_decant' => 5,
        'estado' => 'activo',
        'vence_en' => '2027-05-01T10:00:00-06:00',
    ]])
        ->and($previous)->toBe(['por_pagina' => '1', 'pagina' => '1'])
        ->and(getJson(SOLD_PACKS_ENDPOINT)->assertOk()->assertJsonPath('meta.per_page', 20)->json('data.*.id'))->toBe([$newer->id, $older->id]);
});

it('R35: filters the packs sold by their status now, so vencido also takes an active pack whose validity ended', function () {
    TwoBusinesses::create();
    $overdue = packBoughtIn(TwoBusinesses::FIRST, TwoBusinesses::CUSTOMER, ['remaining_decants' => 3, 'expires_at' => now()->subMinute()]);
    $expired = packBoughtIn(TwoBusinesses::FIRST, TwoBusinesses::CUSTOMER, ['status' => 'expired', 'remaining_decants' => 3, 'expires_at' => now()->subDay()]);
    $active = packBoughtIn(TwoBusinesses::FIRST, TwoBusinesses::CUSTOMER);
    $exhausted = packBoughtIn(TwoBusinesses::FIRST, TwoBusinesses::CUSTOMER_OF_BOTH, ['status' => 'exhausted', 'remaining_decants' => 0]);
    packBoughtIn(TwoBusinesses::SECOND, TwoBusinesses::CUSTOMER_OF_BOTH, ['status' => 'expired', 'remaining_decants' => 3, 'expires_at' => now()->subDay()]);
    TwoBusinesses::actAsOwnerOf(TwoBusinesses::FIRST);

    $expiredNow = getJson(SOLD_PACKS_ENDPOINT.'?estado=vencido')->assertOk()->assertJsonPath('meta.total', 2);

    expect($expiredNow->json('data.*.id'))->toEqualCanonicalizing([$overdue->id, $expired->id])
        ->and($expiredNow->json('data.*.estado'))->toBe(['vencido', 'vencido'])
        ->and(getJson(SOLD_PACKS_ENDPOINT.'?estado=activo')->assertOk()->json('data.*.id'))->toBe([$active->id])
        ->and(getJson(SOLD_PACKS_ENDPOINT.'?estado=agotado')->assertOk()->json('data.*.id'))->toBe([$exhausted->id])
        ->and(getJson(SOLD_PACKS_ENDPOINT.'?estado=pendiente_de_pago')->assertOk()->json('data'))->toBe([]);
});

it('returns 422 for a status in English or unknown and for a page out of range', function (string $query, string $field) {
    actingAsOwner();

    getJson(SOLD_PACKS_ENDPOINT.'?'.$query)->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonStructure(['errores' => [$field]]);
})->with([
    'a status in English' => ['estado=expired', 'estado'],
    'a status packs do not have' => ['estado=cancelado', 'estado'],
    'page zero' => ['pagina=0', 'pagina'],
    'more than 100 per page' => ['por_pagina=101', 'por_pagina'],
]);

it('returns 403 to the staff and the customers on the packs sold', function (string $role) {
    actingAsRole($role);

    getJson(SOLD_PACKS_ENDPOINT)->assertForbidden();
})->with(['colaborador', 'cliente']);

it('returns 403 to the owner and the staff on the packs of the account', function (string $role) {
    actingAsRole($role);

    getJson(PURCHASED_PACKS_ENDPOINT)->assertForbidden();
})->with(['dueno', 'colaborador']);

it('asks for a session on both listings', function (string $path) {
    getJson($path)->assertUnauthorized();
})->with([PURCHASED_PACKS_ENDPOINT, SOLD_PACKS_ENDPOINT]);

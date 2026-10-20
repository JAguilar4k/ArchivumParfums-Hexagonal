<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeVariantModel;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\Catalog\ServesCatalog;
use Tests\Support\Catalog\StoredPerfumes;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

beforeEach(function () {
    TwoBusinesses::create();
    ServesCatalog::register();
    $this->first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->second = TwoBusinesses::idOf(TwoBusinesses::SECOND);
    $this->perfume = StoredPerfumes::create($this->first, variants: [[5, null, 9000], [10, null, null]]);
    $this->other = StoredPerfumes::create($this->first, name: 'Tobacco Vanille', variants: [[50, null, null]]);
    $this->theirs = StoredPerfumes::create($this->second, name: 'Gypsy Water', variants: [[100, null, 150000]]);
    $this->url = "/api/v1/panel/perfumes/{$this->perfume->id->value}/presentaciones";
    TwoBusinesses::actAsOwnerOf(TwoBusinesses::FIRST);
});

it('creates a presentation with its milliliters and an optional manual price and answers 201 with Location', function () {
    $response = postJson($this->url, ['ml' => 50, 'precio_manual' => 98000])->assertCreated();

    $id = $response->json('data.id');
    $response->assertHeader('Location', url($this->url).'/'.$id)
        ->assertJsonPath('data', ['id' => $id, 'ml' => 50, 'precio_venta' => 98000, 'precio_manual' => 98000, 'activa' => true]);

    postJson($this->url, ['ml' => 100])->assertCreated()
        ->assertJsonPath('data.precio_manual', null)
        ->assertJsonPath('data.precio_venta', null);

    $this->scopeQueriesTo($this->first);
    assertDatabaseHas('perfume_variants', ['id' => $id, 'perfume_id' => $this->perfume->id->value, 'size_ml' => 50, 'sale_price_override' => 98000]);
});

it('answers 422 pointing at ml when the milliliters repeat another presentation of the perfume', function () {
    $errors = postJson($this->url, ['ml' => 5])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/datos-invalidos')
        ->json('errores');

    expect(array_keys($errors))->toBe(['ml'])
        ->and($errors['ml'])->toBe(['El perfume ya tiene una presentación con esos mililitros.']);

    // The same milliliters on another perfume are another presentation.
    postJson("/api/v1/panel/perfumes/{$this->other->id->value}/presentaciones", ['ml' => 5])->assertCreated();
});

it('answers 422 with the errors by field when the data is invalid', function (array $body, array $fields) {
    $errors = postJson($this->url, $body)->assertUnprocessable()->json('errores');

    expect(array_keys($errors))->toBe($fields);
})->with([
    'nothing' => [[], ['ml']],
    'zero milliliters' => [['ml' => 0], ['ml']],
    'milliliters as text' => [['ml' => 'cien'], ['ml']],
    'fraction' => [['ml' => 7.5], ['ml']],
    'negative price' => [['ml' => 30, 'precio_manual' => -1], ['precio_manual']],
    'price with a fraction' => [['ml' => 30, 'precio_manual' => 100.5], ['precio_manual']],
]);

it('edits the milliliters, sets and removes the manual price and deactivates and reactivates a presentation', function () {
    $variant = $this->perfume->variants[0];
    $url = "{$this->url}/{$variant->id->value}";

    patchJson($url, ['ml' => 8, 'precio_manual' => 11000])
        ->assertOk()
        ->assertJsonPath('data', ['id' => $variant->id->value, 'ml' => 8, 'precio_venta' => 11000, 'precio_manual' => 11000, 'activa' => true]);

    patchJson($url, ['precio_manual' => null])->assertOk()->assertJsonPath('data.precio_manual', null)->assertJsonPath('data.precio_venta', null);
    patchJson($url, ['activa' => false])->assertOk()->assertJsonPath('data.activa', false)->assertJsonPath('data.ml', 8);
    patchJson($url, ['activa' => true])->assertOk()->assertJsonPath('data.activa', true);

    $this->scopeQueriesTo($this->first);
    assertDatabaseHas('perfume_variants', ['id' => $variant->id->value, 'size_ml' => 8, 'sale_price_override' => null, 'is_active' => true]);
    assertDatabaseCount('perfume_variants', 3);
});

it('answers 422 pointing at ml when an edit would repeat the milliliters of another presentation', function () {
    $errors = patchJson("{$this->url}/{$this->perfume->variants[0]->id->value}", ['ml' => 10])->assertUnprocessable()->json('errores');

    expect(array_keys($errors))->toBe(['ml']);
});

it('lists the active and the inactive presentations with their fields', function () {
    patchJson("{$this->url}/{$this->perfume->variants[1]->id->value}", ['activa' => false])->assertOk();

    $data = getJson($this->url)->assertOk()->json('data');

    expect(array_column($data, 'ml'))->toBe([5, 10])
        ->and(array_column($data, 'activa'))->toBe([true, false])
        ->and($data[0])->toMatchArray(['precio_manual' => 9000, 'precio_venta' => 9000])
        ->and(array_keys($data[0]))->toBe(['id', 'ml', 'precio_venta', 'precio_manual', 'activa']);
});

it('answers 404 in the format of RFC 9457 to the list of an unknown perfume or of one that is not an identity', function (string $id) {
    getJson("/api/v1/panel/perfumes/{$id}/presentaciones")
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/no-encontrado');
})->with([
    'unknown' => '0192f0c4-0000-7000-9000-999999999999',
    'not an identity' => 'no-es-un-uuid',
]);

it('answers 404 to every operation on the perfume of the other business and changes nothing', function () {
    $theirsUrl = "/api/v1/panel/perfumes/{$this->theirs->id->value}/presentaciones";
    $theirVariant = $this->theirs->variants[0];

    getJson($theirsUrl)->assertNotFound();
    postJson($theirsUrl, ['ml' => 50])->assertNotFound();
    patchJson("{$theirsUrl}/{$theirVariant->id->value}", ['ml' => 60, 'activa' => false])->assertNotFound();

    $this->scopeQueriesTo($this->second);
    $stored = PerfumeVariantModel::query()->sole();
    expect($stored->size_ml)->toBe(100)
        ->and($stored->is_active)->toBeTrue();
});

it('answers 404 when the presentation belongs to another perfume of the same business', function () {
    $otherVariant = $this->other->variants[0];

    patchJson("{$this->url}/{$otherVariant->id->value}", ['ml' => 60])->assertNotFound();
    patchJson("{$this->url}/0192f0c4-0000-7000-9000-999999999999", ['ml' => 60])->assertNotFound();
    patchJson("{$this->url}/no-es-un-uuid", ['ml' => 60])->assertNotFound();

    $this->scopeQueriesTo($this->first);
    assertDatabaseHas('perfume_variants', ['id' => $otherVariant->id->value, 'size_ml' => 50]);
});

it('answers 403 to the staff and the customers on every operation', function (string $email) {
    TwoBusinesses::actAs($email);
    $variantUrl = "{$this->url}/{$this->perfume->variants[0]->id->value}";

    getJson($this->url)->assertForbidden()->assertHeader('Content-Type', 'application/problem+json');
    postJson($this->url, ['ml' => 50])->assertForbidden();
    patchJson($variantUrl, ['ml' => 60])->assertForbidden();

    $this->scopeQueriesTo($this->first);
    assertDatabaseCount('perfume_variants', 3);
})->with([
    'advisor' => TwoBusinesses::ADVISOR,
    'customer' => TwoBusinesses::CUSTOMER,
]);

it('answers 401 when the request has no token', function () {
    Auth::forgetGuards();

    getJson($this->url)->assertUnauthorized()->assertHeader('Content-Type', 'application/problem+json');
});

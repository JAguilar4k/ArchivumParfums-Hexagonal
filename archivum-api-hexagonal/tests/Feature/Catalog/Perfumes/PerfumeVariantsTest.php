<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Src\Catalog\Perfumes\Domain\Entities\Perfume;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeVariantModel;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\Catalog\StoredPerfumes;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

/**
 * Two businesses; in the first one, a perfume with two presentations and another with one, and in the second one a perfume
 * with one. The owner of the first signed in.
 *
 * @return array{first: string, second: string, perfume: Perfume, other: Perfume, theirs: Perfume, url: string} The identifier
 *                                                                                                              of each business, the perfumes and the address of the presentations of the first perfume.
 */
function variantsScenario(): array
{
    TwoBusinesses::create();
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $second = TwoBusinesses::idOf(TwoBusinesses::SECOND);
    $perfume = StoredPerfumes::create($first, variants: [[5, null, 9000], [10, null, null]]);
    $other = StoredPerfumes::create($first, name: 'Tobacco Vanille', variants: [[50, null, null]]);
    $theirs = StoredPerfumes::create($second, name: 'Gypsy Water', variants: [[100, null, 150000]]);
    TwoBusinesses::actAsOwnerOf(TwoBusinesses::FIRST);

    return [
        'first' => $first,
        'second' => $second,
        'perfume' => $perfume,
        'other' => $other,
        'theirs' => $theirs,
        'url' => "/api/v1/panel/perfumes/{$perfume->id->value}/presentaciones",
    ];
}

it('creates a presentation with its milliliters and an optional manual price and answers 201 with Location', function () {
    ['first' => $first, 'perfume' => $perfume, 'url' => $url] = variantsScenario();

    $response = postJson($url, ['ml' => 50, 'precio_manual' => 98000])->assertCreated();

    $id = $response->json('data.id');
    $response->assertHeader('Location', url($url).'/'.$id)
        ->assertJsonPath('data', ['id' => $id, 'ml' => 50, 'precio_venta' => 98000, 'precio_manual' => 98000, 'activa' => true]);

    postJson($url, ['ml' => 100])->assertCreated()
        ->assertJsonPath('data.precio_manual', null)
        ->assertJsonPath('data.precio_venta', null);

    $this->scopeQueriesTo($first);
    assertDatabaseHas('perfume_variants', ['id' => $id, 'perfume_id' => $perfume->id->value, 'size_ml' => 50, 'sale_price_override' => 98000]);
});

it('answers 422 pointing at ml when the milliliters repeat another presentation of the perfume', function () {
    ['other' => $other, 'url' => $url] = variantsScenario();

    $errors = postJson($url, ['ml' => 5])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/datos-invalidos')
        ->json('errores');

    expect(array_keys($errors))->toBe(['ml'])
        ->and($errors['ml'])->toBe(['El perfume ya tiene una presentación con esos mililitros.']);

    // The same milliliters on another perfume are another presentation.
    postJson("/api/v1/panel/perfumes/{$other->id->value}/presentaciones", ['ml' => 5])->assertCreated();
});

it('answers 422 with the errors by field when the data is invalid', function (array $body, array $fields) {
    $errors = postJson(variantsScenario()['url'], $body)->assertUnprocessable()->json('errores');

    expect(array_keys($errors))->toBe($fields);
})->with([
    'nothing' => [[], ['ml']],
    'zero milliliters' => [['ml' => 0], ['ml']],
    'milliliters as text' => [['ml' => 'cien'], ['ml']],
    'fraction' => [['ml' => 7.5], ['ml']],
    'negative price' => [['ml' => 30, 'precio_manual' => -1], ['precio_manual']],
    'price with a fraction' => [['ml' => 30, 'precio_manual' => 100.5], ['precio_manual']],
    'price over the integer column' => [['ml' => 30, 'precio_manual' => 2147483648], ['precio_manual']],
]);

it('answers 422 instead of 500 to a manual price over the integer column, when creating and when editing, and changes nothing', function () {
    ['first' => $first, 'perfume' => $perfume, 'url' => $url] = variantsScenario();
    $variant = $perfume->variants[0];

    foreach ([
        postJson($url, ['ml' => 30, 'precio_manual' => 2147483648]),
        patchJson("{$url}/{$variant->id->value}", ['precio_manual' => 2147483648]),
    ] as $response) {
        $response->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/datos-invalidos');

        expect(array_keys($response->json('errores')))->toBe(['precio_manual']);
    }

    // The largest amount the column holds is still accepted.
    postJson($url, ['ml' => 30, 'precio_manual' => 2147483647])->assertCreated()->assertJsonPath('data.precio_manual', 2147483647);

    $this->scopeQueriesTo($first);
    assertDatabaseHas('perfume_variants', ['id' => $variant->id->value, 'size_ml' => 5, 'sale_price_override' => 9000]);
    assertDatabaseCount('perfume_variants', 4);
});

it('edits the milliliters, sets and removes the manual price and deactivates and reactivates a presentation', function () {
    ['first' => $first, 'perfume' => $perfume, 'url' => $baseUrl] = variantsScenario();
    $variant = $perfume->variants[0];
    $url = "{$baseUrl}/{$variant->id->value}";

    patchJson($url, ['ml' => 8, 'precio_manual' => 11000])
        ->assertOk()
        ->assertJsonPath('data', ['id' => $variant->id->value, 'ml' => 8, 'precio_venta' => 11000, 'precio_manual' => 11000, 'activa' => true]);

    patchJson($url, ['precio_manual' => null])->assertOk()->assertJsonPath('data.precio_manual', null)->assertJsonPath('data.precio_venta', null);
    patchJson($url, ['activa' => false])->assertOk()->assertJsonPath('data.activa', false)->assertJsonPath('data.ml', 8);
    patchJson($url, ['activa' => true])->assertOk()->assertJsonPath('data.activa', true);

    $this->scopeQueriesTo($first);
    assertDatabaseHas('perfume_variants', ['id' => $variant->id->value, 'size_ml' => 8, 'sale_price_override' => null, 'is_active' => true]);
    assertDatabaseCount('perfume_variants', 3);
});

it('answers 422 pointing at ml when an edit would repeat the milliliters of another presentation', function () {
    ['perfume' => $perfume, 'url' => $url] = variantsScenario();

    $errors = patchJson("{$url}/{$perfume->variants[0]->id->value}", ['ml' => 10])->assertUnprocessable()->json('errores');

    expect(array_keys($errors))->toBe(['ml']);
});

it('lists the active and the inactive presentations with their fields', function () {
    ['perfume' => $perfume, 'url' => $url] = variantsScenario();
    patchJson("{$url}/{$perfume->variants[1]->id->value}", ['activa' => false])->assertOk();

    $data = getJson($url)->assertOk()->json('data');

    expect(array_column($data, 'ml'))->toBe([5, 10])
        ->and(array_column($data, 'activa'))->toBe([true, false])
        ->and($data[0])->toMatchArray(['precio_manual' => 9000, 'precio_venta' => 9000])
        ->and(array_keys($data[0]))->toBe(['id', 'ml', 'precio_venta', 'precio_manual', 'activa']);
});

it('answers 404 in the format of RFC 9457 to the list of an unknown perfume or of one that is not an identity', function (string $id) {
    variantsScenario();

    getJson("/api/v1/panel/perfumes/{$id}/presentaciones")
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/no-encontrado');
})->with([
    'unknown' => '0192f0c4-0000-7000-9000-999999999999',
    'not an identity' => 'no-es-un-uuid',
]);

it('answers 404 to every operation on the perfume of the other business and changes nothing', function () {
    ['second' => $second, 'theirs' => $theirs] = variantsScenario();
    $theirsUrl = "/api/v1/panel/perfumes/{$theirs->id->value}/presentaciones";
    $theirVariant = $theirs->variants[0];

    getJson($theirsUrl)->assertNotFound();
    postJson($theirsUrl, ['ml' => 50])->assertNotFound();
    patchJson("{$theirsUrl}/{$theirVariant->id->value}", ['ml' => 60, 'activa' => false])->assertNotFound();

    $this->scopeQueriesTo($second);
    $stored = PerfumeVariantModel::query()->sole();
    expect($stored->size_ml)->toBe(100)
        ->and($stored->is_active)->toBeTrue();
});

it('answers 404 when the presentation belongs to another perfume of the same business', function () {
    ['first' => $first, 'other' => $other, 'url' => $url] = variantsScenario();
    $otherVariant = $other->variants[0];

    patchJson("{$url}/{$otherVariant->id->value}", ['ml' => 60])->assertNotFound();
    patchJson("{$url}/0192f0c4-0000-7000-9000-999999999999", ['ml' => 60])->assertNotFound();
    patchJson("{$url}/no-es-un-uuid", ['ml' => 60])->assertNotFound();

    $this->scopeQueriesTo($first);
    assertDatabaseHas('perfume_variants', ['id' => $otherVariant->id->value, 'size_ml' => 50]);
});

it('answers 403 to the staff and the customers on every operation', function (string $email) {
    ['first' => $first, 'perfume' => $perfume, 'url' => $url] = variantsScenario();
    TwoBusinesses::actAs($email);
    $variantUrl = "{$url}/{$perfume->variants[0]->id->value}";

    getJson($url)->assertForbidden()->assertHeader('Content-Type', 'application/problem+json');
    postJson($url, ['ml' => 50])->assertForbidden();
    patchJson($variantUrl, ['ml' => 60])->assertForbidden();

    $this->scopeQueriesTo($first);
    assertDatabaseCount('perfume_variants', 3);
})->with([
    'advisor' => TwoBusinesses::ADVISOR,
    'customer' => TwoBusinesses::CUSTOMER,
]);

it('answers 401 when the request has no token', function () {
    $url = variantsScenario()['url'];
    Auth::forgetGuards();

    getJson($url)->assertUnauthorized()->assertHeader('Content-Type', 'application/problem+json');
});

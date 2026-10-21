<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Src\Catalog\Perfumes\Domain\Entities\Perfume;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeModel;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\Catalog\StoredPerfumes;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\patchJson;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

/**
 * Field errors of a 422 problem, by field name.
 *
 * @param  TestResponse<JsonResponse>  $response
 * @return array<string, list<string>>
 */
function editPerfumeErrorsOf(TestResponse $response): array
{
    $response->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/datos-invalidos');

    return $response->json('errores');
}

/**
 * Two businesses and, in the first, a perfume with two presentations, with the owner of the first signed in.
 *
 * @return array{string, string, Perfume} The identifier of each business and the perfume of the first one.
 */
function editScenario(): array
{
    TwoBusinesses::create();
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $second = TwoBusinesses::idOf(TwoBusinesses::SECOND);
    $perfume = StoredPerfumes::create($first, variants: [[5, null, 9000], [10, null, null]]);
    TwoBusinesses::actAsOwnerOf(TwoBusinesses::FIRST);

    return [$first, $second, $perfume];
}

it('changes only the fields sent and answers 200 with the perfume', function () {
    [$first, , $perfume] = editScenario();

    patchJson("/api/v1/panel/perfumes/{$perfume->id->value}", ['nombre' => 'Oud Wood Intense'])
        ->assertOk()
        ->assertJsonPath('data.id', $perfume->id->value)
        ->assertJsonPath('data.nombre', 'Oud Wood Intense')
        ->assertJsonPath('data.marca', 'Tom Ford')
        ->assertJsonPath('data.concentracion', 'EDP')
        ->assertJsonPath('data.notas.fondo', ['Oud'])
        ->assertJsonPath('data.etiquetas.momento', ['noche']);

    $this->scopeQueriesTo($first);
    assertDatabaseHas('perfumes', ['id' => $perfume->id->value, 'name' => 'Oud Wood Intense', 'brand' => 'Tom Ford']);
});

it('replaces the notes of a layer and the tags of a kind that are sent and keeps the rest', function () {
    [$first, , $perfume] = editScenario();

    patchJson("/api/v1/panel/perfumes/{$perfume->id->value}", [
        'notas' => ['corazon' => ['Rosa', 'Jazmín'], 'fondo' => []],
        'etiquetas' => ['clima' => ['calido']],
    ])
        ->assertOk()
        ->assertJsonPath('data.notas', ['salida' => ['Cardamomo'], 'corazon' => ['Jazmín', 'Rosa'], 'fondo' => []])
        ->assertJsonPath('data.etiquetas', ['momento' => ['noche'], 'clima' => ['calido'], 'ocasion' => ['Cena']]);

    $this->scopeQueriesTo($first);
    assertDatabaseCount('perfume_notes', 3);
    assertDatabaseCount('perfume_tags', 3);
});

it('clears a text that may be missing when it is sent as null', function () {
    [, , $perfume] = editScenario();

    patchJson("/api/v1/panel/perfumes/{$perfume->id->value}", ['concentracion' => null])
        ->assertOk()
        ->assertJsonPath('data.concentracion', null)
        ->assertJsonPath('data.familia', 'Amaderada');
});

it('deactivates the perfume without deleting it and reactivates it', function () {
    [$first, , $perfume] = editScenario();
    $url = "/api/v1/panel/perfumes/{$perfume->id->value}";

    patchJson($url, ['activo' => false])->assertOk()->assertJsonPath('data.activo', false);

    $this->scopeQueriesTo($first);
    assertDatabaseHas('perfumes', ['id' => $perfume->id->value, 'is_active' => false]);
    assertDatabaseCount('perfume_notes', 2);
    assertDatabaseCount('perfume_tags', 3);
    assertDatabaseCount('perfume_variants', 2);

    patchJson($url, ['activo' => true])->assertOk()->assertJsonPath('data.activo', true);

    assertDatabaseHas('perfumes', ['id' => $perfume->id->value, 'is_active' => true]);
});

it('answers 422 with the same rules as the creation', function () {
    [, , $perfume] = editScenario();

    $errors = editPerfumeErrorsOf(patchJson("/api/v1/panel/perfumes/{$perfume->id->value}", [
        'marca' => '',
        'etiquetas' => ['momento' => ['atardecer'], 'clima' => ['hirviendo']],
    ]));

    expect(array_keys($errors))->toBe(['marca', 'etiquetas.momento.0', 'etiquetas.clima.0'])
        ->and($errors['etiquetas.momento.0'])->toBe(['El momento del día debe ser día, tarde o noche.']);
});

it('answers 404 for a perfume that does not exist, in the format of RFC 9457', function (string $id) {
    editScenario();

    patchJson("/api/v1/panel/perfumes/{$id}", ['nombre' => 'Intruso'])
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/no-encontrado');
})->with([
    'unknown' => '0192f0c4-0000-7000-9000-999999999999',
    'not an identity' => 'no-es-un-uuid',
]);

it('answers 404 for the perfume of another business and does not touch it', function () {
    [, $second] = editScenario();
    $theirs = StoredPerfumes::create($second, name: 'Oud Wood');
    TwoBusinesses::actAsOwnerOf(TwoBusinesses::FIRST);

    patchJson("/api/v1/panel/perfumes/{$theirs->id->value}", ['nombre' => 'Intruso', 'activo' => false])->assertNotFound();

    $this->scopeQueriesTo($second);
    $stored = PerfumeModel::query()->findOrFail($theirs->id->value);
    expect($stored->name)->toBe('Oud Wood')
        ->and($stored->is_active)->toBeTrue();
});

it('answers 403 to the staff and the customers and changes nothing', function (string $email) {
    [$first, , $perfume] = editScenario();
    TwoBusinesses::actAs($email);

    patchJson("/api/v1/panel/perfumes/{$perfume->id->value}", ['nombre' => 'Intruso'])
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/problem+json');

    $this->scopeQueriesTo($first);
    assertDatabaseHas('perfumes', ['id' => $perfume->id->value, 'name' => 'Oud Wood']);
})->with([
    'advisor' => TwoBusinesses::ADVISOR,
    'customer' => TwoBusinesses::CUSTOMER,
]);

it('answers 401 when the request has no token', function () {
    [, , $perfume] = editScenario();
    Auth::forgetGuards();

    patchJson("/api/v1/panel/perfumes/{$perfume->id->value}", ['nombre' => 'Intruso'])
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json');
});

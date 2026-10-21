<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeModel;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

const PERFUMES_ENDPOINT = '/api/v1/panel/perfumes';

/**
 * A complete fictitious perfume, with the parts that a test changes given by name.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function perfumePayload(array $overrides = []): array
{
    return [
        'marca' => 'Tom Ford',
        'nombre' => 'Oud Wood',
        'concentracion' => 'EDP',
        'genero' => 'unisex',
        'familia' => 'Amaderada',
        'descripcion' => 'Madera de oud con especias.',
        'notas' => ['salida' => ['Cardamomo'], 'corazon' => ['Palisandro'], 'fondo' => ['Oud', 'Sándalo']],
        'etiquetas' => ['momento' => ['noche'], 'clima' => ['frio'], 'ocasion' => ['Cena']],
        'activo' => true,
        ...$overrides,
    ];
}

/**
 * Field errors of a 422 problem, by field name.
 *
 * @param  TestResponse<JsonResponse>  $response
 * @return array<string, list<string>>
 */
function perfumeErrorsOf(TestResponse $response): array
{
    $response->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/datos-invalidos');

    return $response->json('errores');
}

beforeEach(function () {
    TwoBusinesses::create();
    TwoBusinesses::actAsOwnerOf(TwoBusinesses::FIRST);
});

it('creates the perfume and answers 201 with its Location and only the fields of the resource', function () {
    $response = postJson(PERFUMES_ENDPOINT, perfumePayload())->assertCreated();

    $id = $response->json('data.id');
    $response->assertHeader('Location', url(PERFUMES_ENDPOINT).'/'.$id)
        ->assertJsonPath('data.marca', 'Tom Ford')
        ->assertJsonPath('data.nombre', 'Oud Wood')
        ->assertJsonPath('data.concentracion', 'EDP')
        ->assertJsonPath('data.notas', ['salida' => ['Cardamomo'], 'corazon' => ['Palisandro'], 'fondo' => ['Oud', 'Sándalo']])
        ->assertJsonPath('data.etiquetas', ['momento' => ['noche'], 'clima' => ['frio'], 'ocasion' => ['Cena']])
        ->assertJsonPath('data.activo', true);

    expect(array_keys($response->json('data')))->toBe([
        'id', 'marca', 'nombre', 'concentracion', 'genero', 'familia', 'descripcion', 'notas', 'etiquetas', 'activo',
    ]);

    $this->scopeQueriesTo(TwoBusinesses::idOf(TwoBusinesses::FIRST));
    assertDatabaseHas('perfumes', ['id' => $id, 'brand' => 'Tom Ford', 'concentration' => 'EDP']);
    assertDatabaseCount('perfume_notes', 4);
    assertDatabaseHas('perfume_tags', ['perfume_id' => $id, 'kind' => 'time_of_day', 'value' => 'night']);
    assertDatabaseHas('perfume_tags', ['perfume_id' => $id, 'kind' => 'climate', 'value' => 'cold']);
});

it('needs only the brand and the name and creates the perfume active and without notes or tags', function () {
    postJson(PERFUMES_ENDPOINT, ['marca' => 'Maison Margiela', 'nombre' => 'Replica Jazz Club'])
        ->assertCreated()
        ->assertJsonPath('data.concentracion', null)
        ->assertJsonPath('data.notas', ['salida' => [], 'corazon' => [], 'fondo' => []])
        ->assertJsonPath('data.etiquetas', ['momento' => [], 'clima' => [], 'ocasion' => []])
        ->assertJsonPath('data.activo', true);
});

it('creates the perfume inactive when the owner sends activo false', function () {
    postJson(PERFUMES_ENDPOINT, perfumePayload(['activo' => false]))
        ->assertCreated()
        ->assertJsonPath('data.activo', false);
});

it('answers 422 with the errors by field in Spanish when the brand and the name are missing', function () {
    $errors = perfumeErrorsOf(postJson(PERFUMES_ENDPOINT, []));

    expect(array_keys($errors))->toBe(['marca', 'nombre'])
        ->and($errors['marca'][0])->toContain('marca');
});

it('answers 422 on the moment and the climate outside the values of the catalog', function () {
    $errors = perfumeErrorsOf(postJson(PERFUMES_ENDPOINT, perfumePayload([
        'etiquetas' => ['momento' => ['dia', 'atardecer'], 'clima' => ['frio', 'hirviendo']],
    ])));

    expect($errors['etiquetas.momento.1'])->toBe(['El momento del día debe ser día, tarde o noche.'])
        ->and($errors['etiquetas.clima.1'])->toBe(['El clima debe ser cálido, templado o frío.'])
        ->and($errors)->toHaveCount(2);
});

it('answers 422 on a text that is too long and on a repeated note', function () {
    $errors = perfumeErrorsOf(postJson(PERFUMES_ENDPOINT, perfumePayload([
        'marca' => str_repeat('a', 121),
        'notas' => ['fondo' => ['Oud', 'oud']],
    ])));

    expect(array_keys($errors))->toContain('marca', 'notas.fondo.0', 'notas.fondo.1');
});

it('answers 422 pointing at the name when the perfume is repeated in the same business', function () {
    postJson(PERFUMES_ENDPOINT, perfumePayload())->assertCreated();

    $errors = perfumeErrorsOf(postJson(PERFUMES_ENDPOINT, perfumePayload(['marca' => 'TOM FORD', 'nombre' => 'oud wood'])));

    expect(array_keys($errors))->toBe(['nombre'])
        ->and($errors['nombre'])->toBe(['Ya existe un perfume con esa marca, nombre y concentración en la tienda.']);

    // A second request still works: the failed one did not leave the transaction broken.
    postJson(PERFUMES_ENDPOINT, perfumePayload(['concentracion' => 'EDT']))->assertCreated();

    $this->scopeQueriesTo(TwoBusinesses::idOf(TwoBusinesses::FIRST));
    expect(PerfumeModel::query()->count())->toBe(2);
});

it('assigns the perfume to the business of the session even when the body names another one', function () {
    $second = TwoBusinesses::idOf(TwoBusinesses::SECOND);

    $response = postJson(PERFUMES_ENDPOINT, perfumePayload(['business_id' => $second, 'tienda_id' => $second, 'tienda' => TwoBusinesses::SECOND]))
        ->assertCreated();

    expect($response->json('data'))->not->toHaveKeys(['business_id', 'tienda_id', 'tienda']);

    $this->scopeQueriesTo($second);
    assertDatabaseCount('perfumes', 0);

    $this->scopeQueriesTo(TwoBusinesses::idOf(TwoBusinesses::FIRST));
    assertDatabaseHas('perfumes', ['id' => $response->json('data.id')]);
});

it('lets two businesses register the same perfume and keeps each one in its own catalog', function () {
    postJson(PERFUMES_ENDPOINT, perfumePayload())->assertCreated();

    TwoBusinesses::actAsOwnerOf(TwoBusinesses::SECOND);
    postJson(PERFUMES_ENDPOINT, perfumePayload())->assertCreated();

    $this->scopeQueriesTo(TwoBusinesses::idOf(TwoBusinesses::FIRST));
    expect(PerfumeModel::query()->pluck('business_id')->all())->toBe([TwoBusinesses::idOf(TwoBusinesses::FIRST)]);

    $this->scopeQueriesTo(TwoBusinesses::idOf(TwoBusinesses::SECOND));
    expect(PerfumeModel::query()->pluck('business_id')->all())->toBe([TwoBusinesses::idOf(TwoBusinesses::SECOND)]);
});

it('answers 403 in the format of RFC 9457 to the staff and the customers and stores nothing', function (string $email) {
    TwoBusinesses::actAs($email);

    postJson(PERFUMES_ENDPOINT, perfumePayload())
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/prohibido');

    $this->scopeQueriesTo(TwoBusinesses::idOf(TwoBusinesses::FIRST));
    assertDatabaseCount('perfumes', 0);
})->with([
    'advisor' => TwoBusinesses::ADVISOR,
    'customer' => TwoBusinesses::CUSTOMER,
]);

it('answers 401 in the format of RFC 9457 when the request has no token', function () {
    Auth::forgetGuards();

    postJson(PERFUMES_ENDPOINT, perfumePayload())
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/no-autenticado');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Src\Catalog\Perfumes\Domain\Entities\Perfume;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\Catalog\StoredPerfumes;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\getJson;

pest()->use(RefreshDatabaseAsOwner::class);

const PANEL_PERFUMES = '/api/v1/panel/perfumes';

/**
 * Two businesses with three perfumes in the first one (one of them inactive) and one in the second, with the owner of
 * the first signed in.
 *
 * @return array{oud: Perfume, tobacco: Perfume, jazz: Perfume, theirs: Perfume}
 */
function listScenario(): array
{
    TwoBusinesses::create();
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $second = TwoBusinesses::idOf(TwoBusinesses::SECOND);
    $scenario = [
        'oud' => StoredPerfumes::create($first, 'Tom Ford', 'Oud Wood', variants: [[5, 9500, 10000], [10, null, null]]),
        'tobacco' => StoredPerfumes::create($first, 'Tom Ford', 'Tobacco Vanille', 'EDP'),
        'jazz' => StoredPerfumes::create($first, 'Maison Margiela', 'Replica Jazz Club', 'EDT', active: false),
        'theirs' => StoredPerfumes::create($second, 'Byredo', 'Gypsy Water', null),
    ];
    TwoBusinesses::actAsOwnerOf(TwoBusinesses::FIRST);

    return $scenario;
}

it('lists the active and the inactive perfumes of the business with data, links and meta', function () {
    listScenario();

    $response = getJson(PANEL_PERFUMES)->assertOk()->assertJsonStructure(['data', 'links', 'meta']);

    expect(array_column($response->json('data'), 'nombre'))->toBe(['Replica Jazz Club', 'Oud Wood', 'Tobacco Vanille'])
        ->and(array_column($response->json('data'), 'activo'))->toBe([false, true, true])
        ->and($response->json('meta.total'))->toBe(3)
        ->and($response->json('meta.per_page'))->toBe(20);
});

it('never lists the perfumes of the other business', function () {
    listScenario();
    $names = array_column(getJson(PANEL_PERFUMES)->json('data'), 'nombre');

    expect($names)->not->toContain('Gypsy Water');

    TwoBusinesses::actAsOwnerOf(TwoBusinesses::SECOND);

    expect(array_column(getJson(PANEL_PERFUMES)->assertOk()->json('data'), 'nombre'))->toBe(['Gypsy Water']);
});

it('searches the brand and the name ignoring case', function (string $text, array $expected) {
    listScenario();

    $response = getJson(PANEL_PERFUMES.'?texto='.urlencode($text))->assertOk();

    expect(array_column($response->json('data'), 'nombre'))->toBe($expected)
        ->and($response->json('meta.total'))->toBe(count($expected));
})->with([
    'brand in upper case' => ['TOM FORD', ['Oud Wood', 'Tobacco Vanille']],
    'name in lower case' => ['jazz club', ['Replica Jazz Club']],
    'part of a word' => ['vanil', ['Tobacco Vanille']],
    'nothing' => ['inexistente', []],
]);

it('searches a percent sign as text and not as a wildcard', function () {
    listScenario();

    expect(getJson(PANEL_PERFUMES.'?texto='.urlencode('%'))->assertOk()->json('data'))->toBe([]);
});

it('pages the list with pagina and por_pagina', function () {
    listScenario();

    $second = getJson(PANEL_PERFUMES.'?por_pagina=2&pagina=2')->assertOk();

    expect(array_column($second->json('data'), 'nombre'))->toBe(['Tobacco Vanille'])
        ->and($second->json('meta.current_page'))->toBe(2)
        ->and($second->json('meta.last_page'))->toBe(2)
        ->and($second->json('meta.total'))->toBe(3);
});

it('answers 422 to a page or a size out of range', function (string $query, string $field) {
    listScenario();

    getJson(PANEL_PERFUMES.'?'.$query)
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonStructure(['errores' => [$field]]);
})->with([
    'page zero' => ['pagina=0', 'pagina'],
    'size over the maximum' => ['por_pagina=101', 'por_pagina'],
    'size zero' => ['por_pagina=0', 'por_pagina'],
]);

it('shows the detail with the notes by layer, the tags by kind and every presentation', function () {
    $oud = listScenario()['oud'];

    $response = getJson(PANEL_PERFUMES.'/'.$oud->id->value)
        ->assertOk()
        ->assertJsonPath('data.id', $oud->id->value)
        ->assertJsonPath('data.notas', ['salida' => ['Cardamomo'], 'corazon' => [], 'fondo' => ['Oud']])
        ->assertJsonPath('data.etiquetas', ['momento' => ['noche'], 'clima' => ['frio'], 'ocasion' => ['Cena']]);

    $variants = $response->json('data.presentaciones');
    expect(array_column($variants, 'ml'))->toBe([5, 10])
        ->and($variants[0])->toMatchArray(['precio_venta' => 10000, 'precio_manual' => 10000, 'activa' => true])
        ->and($variants[1])->toMatchArray(['precio_venta' => null, 'precio_manual' => null, 'activa' => true])
        ->and(array_keys($variants[0]))->toBe(['id', 'ml', 'precio_venta', 'precio_manual', 'activa']);
});

it('shows an inactive perfume to its owner', function () {
    $jazz = listScenario()['jazz'];

    getJson(PANEL_PERFUMES.'/'.$jazz->id->value)->assertOk()->assertJsonPath('data.activo', false);
});

it('answers 404 to the detail of a perfume that does not exist, in the format of RFC 9457', function (string $path) {
    listScenario();

    getJson(PANEL_PERFUMES.'/'.$path)
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/no-encontrado');
})->with([
    'unknown' => '0192f0c4-0000-7000-9000-999999999999',
    'not an identity' => 'no-es-un-uuid',
]);

it('answers 404 to the detail of a perfume of the other business', function () {
    $theirs = listScenario()['theirs'];

    getJson(PANEL_PERFUMES.'/'.$theirs->id->value)->assertNotFound();
});

it('answers 403 to the staff and the customers on the list and on the detail', function (string $email) {
    $oud = listScenario()['oud'];
    TwoBusinesses::actAs($email);

    getJson(PANEL_PERFUMES)->assertForbidden()->assertHeader('Content-Type', 'application/problem+json');
    getJson(PANEL_PERFUMES.'/'.$oud->id->value)->assertForbidden();
})->with([
    'advisor' => TwoBusinesses::ADVISOR,
    'customer' => TwoBusinesses::CUSTOMER,
]);

it('answers 401 when the request has no token', function () {
    listScenario();
    Auth::forgetGuards();

    getJson(PANEL_PERFUMES)->assertUnauthorized()->assertHeader('Content-Type', 'application/problem+json');
});

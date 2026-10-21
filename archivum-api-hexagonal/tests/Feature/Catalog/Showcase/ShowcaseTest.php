<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Src\Catalog\Perfumes\Domain\ValueObjects\Climate;
use Src\Catalog\Perfumes\Domain\ValueObjects\NoteLayer;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeNote;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeTag;
use Src\Catalog\Perfumes\Domain\ValueObjects\TimeOfDay;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\Catalog\ServesCatalog;
use Tests\Support\Catalog\StoredPerfumes;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\getJson;

pest()->use(RefreshDatabaseAsOwner::class);

/**
 * The names of the perfumes the showcase of a store answers for a query string.
 *
 * @return list<string>
 */
function showcaseNames(string $slug, string $query = ''): array
{
    return array_column(getJson("/api/v1/tiendas/{$slug}/perfumes{$query}")->assertOk()->json('data'), 'nombre');
}

beforeEach(function () {
    Storage::fake('public');
    TwoBusinesses::create();
    ServesCatalog::register();
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $second = TwoBusinesses::idOf(TwoBusinesses::SECOND);

    // Visible: active, with an active presentation that has a sale price.
    StoredPerfumes::create($first, 'Tom Ford', 'Oud Wood', 'EDP', variants: [[50, 185000, 185000], [100, 295000, 295000]], photoPath: 'perfumes/oud.png');
    StoredPerfumes::create(
        $first, 'Casa Aurora', 'Alba de Azahar', 'EDT', variants: [[50, 98000, 98000]], family: 'Floral',
        notes: [new PerfumeNote(NoteLayer::Top, 'Neroli'), new PerfumeNote(NoteLayer::Heart, 'Azahar')],
        tags: [PerfumeTag::timeOfDay(TimeOfDay::Day), PerfumeTag::climate(Climate::Warm), PerfumeTag::occasion('Boda')],
    );
    StoredPerfumes::create(
        $first, 'Atelier Brisa', 'Sal Marina', 'EDT', variants: [[100, 120000, 120000]], family: 'Acuática',
        notes: [new PerfumeNote(NoteLayer::Top, 'Limón')],
        tags: [PerfumeTag::timeOfDay(TimeOfDay::Day), PerfumeTag::timeOfDay(TimeOfDay::Afternoon), PerfumeTag::climate(Climate::Warm), PerfumeTag::occasion('Playa')],
    );
    // Visible, but only with the presentation that can be sold.
    StoredPerfumes::create($first, 'Zeta', 'Mixto', 'EDP', variants: [[30, 60000, 60000], [60, null, null], [90, 70000, 70000]], inactiveSizes: [90]);
    // Hidden: inactive, without presentations, without a sale price, and with every presentation inactive.
    StoredPerfumes::create($first, 'Oculto', 'Inactivo', active: false, variants: [[50, 50000, 50000]]);
    StoredPerfumes::create($first, 'Oculto', 'Sin presentaciones');
    StoredPerfumes::create($first, 'Oculto', 'Sin precio', variants: [[50, null, null]]);
    StoredPerfumes::create($first, 'Oculto', 'Presentaciones inactivas', variants: [[50, 50000, 50000]], inactiveSizes: [50]);
    StoredPerfumes::create($second, 'Byredo', 'Gypsy Water', 'EDP', variants: [[100, 150000, 150000]]);
    // Visitors do not sign in.
    Auth::forgetGuards();
});

it('lists, without authentication, only the active perfumes that have a presentation that can be sold, ordered by brand and name', function () {
    expect(showcaseNames(TwoBusinesses::FIRST))->toBe(['Sal Marina', 'Alba de Azahar', 'Oud Wood', 'Mixto']);
});

it('shows each perfume with its public fields and only the presentations that can be sold', function () {
    $data = getJson('/api/v1/tiendas/'.TwoBusinesses::FIRST.'/perfumes')->assertOk()->json('data');
    $oud = collect($data)->firstWhere('nombre', 'Oud Wood');
    $mixed = collect($data)->firstWhere('nombre', 'Mixto');

    expect(array_keys($oud))->toBe(['id', 'marca', 'nombre', 'concentracion', 'familia', 'foto_url', 'etiquetas', 'presentaciones'])
        ->and($oud['foto_url'])->toBe(Storage::disk('public')->url('perfumes/oud.png'))
        ->and($oud['etiquetas'])->toBe(['momento' => ['noche'], 'clima' => ['frio'], 'ocasion' => ['Cena']])
        ->and($oud['presentaciones'])->toBe([['ml' => 50, 'precio_venta' => 185000], ['ml' => 100, 'precio_venta' => 295000]])
        ->and($mixed['presentaciones'])->toBe([['ml' => 30, 'precio_venta' => 60000]])
        ->and($mixed['foto_url'])->toBeNull();
});

it('filters by text in the brand or the name ignoring case', function (string $text, array $expected) {
    expect(showcaseNames(TwoBusinesses::FIRST, '?texto='.urlencode($text)))->toBe($expected);
})->with([
    'brand' => ['TOM FORD', ['Oud Wood']],
    'name' => ['azahar', ['Alba de Azahar']],
    'nothing' => ['inexistente', []],
]);

it('filters by family, by note, by moment, by climate and by occasion', function (string $query, array $expected) {
    expect(showcaseNames(TwoBusinesses::FIRST, $query))->toBe($expected);
})->with([
    'family' => ['?familia=floral', ['Alba de Azahar']],
    'note' => ['?nota=nero', ['Alba de Azahar']],
    'note of the base' => ['?nota=oud', ['Oud Wood', 'Mixto']],
    'moment' => ['?momento=tarde', ['Sal Marina']],
    'moment of the night' => ['?momento=noche', ['Oud Wood', 'Mixto']],
    'climate' => ['?clima=calido', ['Sal Marina', 'Alba de Azahar']],
    'occasion' => ['?ocasion=playa', ['Sal Marina']],
]);

it('filters by the maximum price of some presentation that can be sold', function () {
    expect(showcaseNames(TwoBusinesses::FIRST, '?precio_maximo=100000'))->toBe(['Alba de Azahar', 'Mixto'])
        ->and(showcaseNames(TwoBusinesses::FIRST, '?precio_maximo=185000'))->toBe(['Sal Marina', 'Alba de Azahar', 'Oud Wood', 'Mixto'])
        ->and(showcaseNames(TwoBusinesses::FIRST, '?precio_maximo=59999'))->toBe([]);
});

it('does not count the price of a presentation that cannot be sold', function () {
    // The perfume Mixto has a presentation of 90 ml for 70 000, but it is inactive.
    expect(showcaseNames(TwoBusinesses::FIRST, '?precio_maximo=65000&texto=mixto'))->toBe(['Mixto'])
        ->and(showcaseNames(TwoBusinesses::FIRST, '?precio_maximo=59000&texto=mixto'))->toBe([]);
});

it('combines the filters', function () {
    expect(showcaseNames(TwoBusinesses::FIRST, '?momento=dia&clima=calido'))->toBe(['Sal Marina', 'Alba de Azahar'])
        ->and(showcaseNames(TwoBusinesses::FIRST, '?momento=dia&clima=calido&precio_maximo=100000'))->toBe(['Alba de Azahar'])
        ->and(showcaseNames(TwoBusinesses::FIRST, '?momento=dia&clima=frio'))->toBe([]);
});

it('pages the showcase', function () {
    $second = getJson('/api/v1/tiendas/'.TwoBusinesses::FIRST.'/perfumes?por_pagina=3&pagina=2')
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect(array_column($second->json('data'), 'nombre'))->toBe(['Mixto'])
        ->and($second->json('meta.total'))->toBe(4)
        ->and($second->json('meta.per_page'))->toBe(3)
        ->and($second->json('meta.last_page'))->toBe(2);
});

it('answers 422 to a filter that cannot match anything', function (string $query, string $field) {
    getJson('/api/v1/tiendas/'.TwoBusinesses::FIRST.'/perfumes'.$query)
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/datos-invalidos')
        ->assertJsonStructure(['errores' => [$field]]);
})->with([
    'moment outside the list' => ['?momento=madrugada', 'momento'],
    'climate outside the list' => ['?clima=hirviendo', 'clima'],
    'negative price' => ['?precio_maximo=-1', 'precio_maximo'],
    'price that is not a number' => ['?precio_maximo=barato', 'precio_maximo'],
    'page zero' => ['?pagina=0', 'pagina'],
    'size over the maximum' => ['?por_pagina=101', 'por_pagina'],
]);

it('answers 404 in the format of RFC 9457 to a store that does not exist', function () {
    getJson('/api/v1/tiendas/perfumeria-inexistente/perfumes')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/no-encontrado');
});

it('never includes the perfumes of another store, whatever the filters', function () {
    expect(showcaseNames(TwoBusinesses::SECOND))->toBe(['Gypsy Water'])
        ->and(showcaseNames(TwoBusinesses::SECOND, '?texto=oud'))->toBe([])
        ->and(showcaseNames(TwoBusinesses::SECOND, '?precio_maximo=1000000'))->toBe(['Gypsy Water'])
        ->and(showcaseNames(TwoBusinesses::FIRST, '?texto=gypsy'))->toBe([]);
});

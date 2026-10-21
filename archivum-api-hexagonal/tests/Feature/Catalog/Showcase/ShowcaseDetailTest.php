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

/** The address of the detail of a perfume under the slug of a store. */
function showcaseDetailUrl(string $slug, string $perfumeId): string
{
    return "/api/v1/tiendas/{$slug}/perfumes/{$perfumeId}";
}

beforeEach(function () {
    Storage::fake('public');
    TwoBusinesses::create();
    ServesCatalog::register();
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $second = TwoBusinesses::idOf(TwoBusinesses::SECOND);

    $this->visible = StoredPerfumes::create(
        $first, 'Maison Noctis', 'Oud Nocturno', 'EDP', variants: [[30, 60000, 60000], [60, null, null], [90, 70000, 70000], [100, 295000, 295000]],
        notes: [
            new PerfumeNote(NoteLayer::Top, 'Cardamomo'), new PerfumeNote(NoteLayer::Heart, 'Rosa'),
            new PerfumeNote(NoteLayer::Base, 'Oud'), new PerfumeNote(NoteLayer::Base, 'Ámbar'),
        ],
        tags: [PerfumeTag::timeOfDay(TimeOfDay::Night), PerfumeTag::climate(Climate::Cold), PerfumeTag::occasion('Cena')],
        inactiveSizes: [90],
        photoPath: 'perfumes/oud.png',
    );
    $this->inactive = StoredPerfumes::create($first, 'Oculto', 'Inactivo', active: false, variants: [[50, 50000, 50000]]);
    $this->withoutVariants = StoredPerfumes::create($first, 'Oculto', 'Sin presentaciones');
    $this->withoutPrice = StoredPerfumes::create($first, 'Oculto', 'Sin precio', variants: [[50, null, null]]);
    $this->allInactive = StoredPerfumes::create($first, 'Oculto', 'Presentaciones inactivas', variants: [[50, 50000, 50000]], inactiveSizes: [50]);
    $this->theirs = StoredPerfumes::create($second, 'Byredo', 'Gypsy Water', 'EDP', variants: [[100, 150000, 150000]]);
    // Visitors do not sign in.
    Auth::forgetGuards();
});

it('shows, without authentication, the detail of a perfume with its notes by layer, its tags and the presentations that can be sold', function () {
    $response = getJson(showcaseDetailUrl(TwoBusinesses::FIRST, $this->visible->id->value))->assertOk();

    expect(array_keys($response->json('data')))->toBe([
        'id', 'marca', 'nombre', 'concentracion', 'genero', 'familia', 'descripcion', 'foto_url', 'notas', 'etiquetas', 'presentaciones',
    ]);

    $response->assertJsonPath('data.id', $this->visible->id->value)
        ->assertJsonPath('data.marca', 'Maison Noctis')
        ->assertJsonPath('data.nombre', 'Oud Nocturno')
        ->assertJsonPath('data.concentracion', 'EDP')
        ->assertJsonPath('data.genero', 'unisex')
        ->assertJsonPath('data.familia', 'Amaderada')
        ->assertJsonPath('data.descripcion', 'Madera de oud con especias.')
        ->assertJsonPath('data.foto_url', Storage::disk('public')->url('perfumes/oud.png'))
        ->assertJsonPath('data.notas', ['salida' => ['Cardamomo'], 'corazon' => ['Rosa'], 'fondo' => ['Oud', 'Ámbar']])
        ->assertJsonPath('data.etiquetas', ['momento' => ['noche'], 'clima' => ['frio'], 'ocasion' => ['Cena']]);
});

it('shows only the presentations that are active and have a sale price, with their milliliters and price in whole colones', function () {
    $presentations = getJson(showcaseDetailUrl(TwoBusinesses::FIRST, $this->visible->id->value))->assertOk()->json('data.presentaciones');

    expect($presentations)->toBe([
        ['ml' => 30, 'precio_venta' => 60000],
        ['ml' => 100, 'precio_venta' => 295000],
    ]);
});

it('answers 404 in the format of RFC 9457 to a perfume that is inactive, has nothing to sell or does not exist', function (string $perfume) {
    getJson(showcaseDetailUrl(TwoBusinesses::FIRST, $this->{$perfume}->id->value))
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/no-encontrado');
})->with([
    'an inactive perfume' => 'inactive',
    'a perfume without presentations' => 'withoutVariants',
    'a perfume without a sale price' => 'withoutPrice',
    'a perfume whose presentations are all inactive' => 'allInactive',
]);

it('answers 404 to an identity that does not exist or is not one', function (string $id) {
    getJson(showcaseDetailUrl(TwoBusinesses::FIRST, $id))
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json');
})->with([
    'unknown' => '0192f0c4-0000-7000-9000-999999999999',
    'not an identity' => 'no-es-un-uuid',
]);

it('answers 404 to the perfume of another store asked with the slug of this one, exactly like to one that does not exist', function () {
    getJson(showcaseDetailUrl(TwoBusinesses::FIRST, $this->theirs->id->value))->assertNotFound();
    getJson(showcaseDetailUrl(TwoBusinesses::SECOND, $this->visible->id->value))->assertNotFound();

    getJson(showcaseDetailUrl(TwoBusinesses::SECOND, $this->theirs->id->value))
        ->assertOk()
        ->assertJsonPath('data.nombre', 'Gypsy Water');
});

it('answers 404 to a store that does not exist', function () {
    getJson(showcaseDetailUrl('perfumeria-inexistente', $this->visible->id->value))->assertNotFound();
});

it('follows the same rule of visibility as the list: it shows exactly the perfumes the list shows', function () {
    $listed = array_column(getJson('/api/v1/tiendas/'.TwoBusinesses::FIRST.'/perfumes')->assertOk()->json('data'), 'id');

    foreach (['visible', 'inactive', 'withoutVariants', 'withoutPrice', 'allInactive'] as $name) {
        $id = $this->{$name}->id->value;
        $status = getJson(showcaseDetailUrl(TwoBusinesses::FIRST, $id))->status();

        expect($status === 200)->toBe(in_array($id, $listed, true), "The perfume {$name} is shown by the detail and the list in different ways.");
    }
});

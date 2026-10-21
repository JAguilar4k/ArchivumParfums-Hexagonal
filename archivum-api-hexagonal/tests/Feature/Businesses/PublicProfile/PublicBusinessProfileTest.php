<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\Catalog\ServesCatalog;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\getJson;

pest()->use(RefreshDatabaseAsOwner::class);

beforeEach(function () {
    Storage::fake('public');
    TwoBusinesses::create();
    ServesCatalog::register();
    DB::table('businesses')->where('slug', TwoBusinesses::FIRST)->update(['location_text' => 'Escazú, frente al parque', 'logo_path' => 'logos/primera.png']);
    // Visitors do not sign in.
    Auth::forgetGuards();
});

it('shows the public profile of a business by its slug without authentication', function () {
    getJson('/api/v1/tiendas/'.TwoBusinesses::FIRST)
        ->assertOk()
        ->assertExactJson(['data' => [
            'nombre' => TwoBusinesses::NAMES[TwoBusinesses::FIRST],
            'slug' => TwoBusinesses::FIRST,
            'logo_url' => Storage::disk('public')->url('logos/primera.png'),
            'lugar_cita' => 'Escazú, frente al parque',
            'zona_horaria' => 'America/Costa_Rica',
        ]]);
});

it('answers null for the logo and the place of a business that has none', function () {
    getJson('/api/v1/tiendas/'.TwoBusinesses::SECOND)
        ->assertOk()
        ->assertJsonPath('data.logo_url', null)
        ->assertJsonPath('data.lugar_cita', null);
});

it('exposes only the fields of the resource: no settings, no staff and no identifiers', function () {
    $fields = array_keys(getJson('/api/v1/tiendas/'.TwoBusinesses::FIRST)->assertOk()->json('data'));

    expect($fields)->toBe(['nombre', 'slug', 'logo_url', 'lugar_cita', 'zona_horaria']);
});

it('gives each slug only the data of its own business', function () {
    foreach ([TwoBusinesses::FIRST, TwoBusinesses::SECOND] as $slug) {
        getJson("/api/v1/tiendas/{$slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $slug)
            ->assertJsonPath('data.nombre', TwoBusinesses::NAMES[$slug]);
    }
});

it('answers 404 in the format of RFC 9457 to a business that does not exist or a slug that cannot be one', function (string $slug) {
    getJson("/api/v1/tiendas/{$slug}")
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/no-encontrado');
})->with([
    'unknown' => 'perfumeria-inexistente',
    'uppercase' => 'PERFUMERIA',
    'with spaces' => 'una%20tienda',
]);

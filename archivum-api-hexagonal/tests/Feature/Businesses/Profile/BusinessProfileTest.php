<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;

pest()->use(RefreshDatabaseAsOwner::class);

const PROFILE_ENDPOINT = '/api/v1/panel/tienda';

/**
 * Settings stored in the row of a business, as JSON.
 *
 * @return array<string, mixed>
 */
function storedSettingsOf(string $slug): array
{
    return json_decode((string) DB::table('businesses')->where('slug', $slug)->value('settings'), true);
}

/**
 * Field errors of a 422 problem, by field name.
 *
 * @param  TestResponse<JsonResponse>  $response
 * @return array<string, list<string>>
 */
function fieldErrorsOf(TestResponse $response): array
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

it('shows the profile of the business of the session with the default settings', function () {
    getJson(PROFILE_ENDPOINT)
        ->assertOk()
        ->assertJsonPath('data.id', TwoBusinesses::idOf(TwoBusinesses::FIRST))
        ->assertJsonPath('data.slug', TwoBusinesses::FIRST)
        ->assertJsonPath('data.zona_horaria', 'America/Costa_Rica')
        ->assertJsonPath('data.ajustes', [
            'margen_botella' => 5000,
            'tamanos_decant_ml' => [2, 5, 10],
            'anticipacion_minima_horas' => 12,
            'horizonte_reserva_dias' => 30,
            'plazo_cambio_cita_horas' => 2,
            'apartado_lista_espera_horas' => 2,
            'vencimiento_sin_pago_minutos' => 30,
            'recordatorios_horas' => [24, 2],
            'pasarela_por_defecto' => 'onvo',
            'tipo_cambio_usd' => null,
        ]);
});

it('changes only the fields sent and stores only the settings that differ from the defaults', function () {
    $name = DB::table('businesses')->where('slug', TwoBusinesses::FIRST)->value('name');

    patchJson(PROFILE_ENDPOINT, ['ajustes' => ['margen_botella' => 6000]])
        ->assertOk()
        ->assertJsonPath('data.nombre', $name)
        ->assertJsonPath('data.ajustes.margen_botella', 6000)
        ->assertJsonPath('data.ajustes.tamanos_decant_ml', [2, 5, 10]);

    expect(storedSettingsOf(TwoBusinesses::FIRST))->toBe(['bottle_margin_colones' => 6000]);
});

it('changes the name, the place and the timezone but never the slug', function () {
    patchJson(PROFILE_ENDPOINT, [
        'nombre' => 'Archivum Parfums',
        'lugar_cita' => 'Café del parque central de Escazú',
        'zona_horaria' => 'America/Panama',
        'slug' => 'otra-tienda',
    ])
        ->assertOk()
        ->assertJsonPath('data.nombre', 'Archivum Parfums')
        ->assertJsonPath('data.lugar_cita', 'Café del parque central de Escazú')
        ->assertJsonPath('data.zona_horaria', 'America/Panama')
        ->assertJsonPath('data.slug', TwoBusinesses::FIRST);

    $row = DB::table('businesses')->where('id', TwoBusinesses::idOf(TwoBusinesses::FIRST))->first();

    expect($row?->name)->toBe('Archivum Parfums')
        ->and($row?->location_text)->toBe('Café del parque central de Escazú')
        ->and($row?->timezone)->toBe('America/Panama')
        ->and($row?->slug)->toBe(TwoBusinesses::FIRST);
});

it('clears the place of the appointment with null', function () {
    patchJson(PROFILE_ENDPOINT, ['lugar_cita' => 'Café del parque central'])->assertOk();

    patchJson(PROFILE_ENDPOINT, ['lugar_cita' => null])->assertOk()->assertJsonPath('data.lugar_cita', null);

    expect(DB::table('businesses')->where('slug', TwoBusinesses::FIRST)->value('location_text'))->toBeNull();
});

it('accepts and keeps each valid setting', function (string $field, mixed $value) {
    patchJson(PROFILE_ENDPOINT, ['ajustes' => [$field => $value]])
        ->assertOk()
        ->assertJsonPath("data.ajustes.{$field}", $value);

    getJson(PROFILE_ENDPOINT)->assertJsonPath("data.ajustes.{$field}", $value);
})->with([
    'bottle margin' => ['margen_botella', 7500],
    'free bottle margin' => ['margen_botella', 0],
    'decant sizes' => ['tamanos_decant_ml', [3, 8, 15]],
    'minimum notice' => ['anticipacion_minima_horas', 24],
    'notice as long as the stored horizon' => ['anticipacion_minima_horas', 720],
    'booking horizon' => ['horizonte_reserva_dias', 60],
    'horizon as short as the stored notice' => ['horizonte_reserva_dias', 1],
    'appointment change cutoff' => ['plazo_cambio_cita_horas', 6],
    'waitlist hold' => ['apartado_lista_espera_horas', 4],
    'unpaid expiry' => ['vencimiento_sin_pago_minutos', 45],
    'reminders' => ['recordatorios_horas', [48, 3]],
    'no reminders' => ['recordatorios_horas', []],
    'paypal gateway' => ['pasarela_por_defecto', 'paypal'],
    'exchange rate' => ['tipo_cambio_usd', 512.5],
]);

it('accepts a horizon as long as the notice sent with it', function () {
    patchJson(PROFILE_ENDPOINT, ['ajustes' => ['anticipacion_minima_horas' => 48, 'horizonte_reserva_dias' => 2]])
        ->assertOk()
        ->assertJsonPath('data.ajustes.anticipacion_minima_horas', 48)
        ->assertJsonPath('data.ajustes.horizonte_reserva_dias', 2);
});

it('reads a number sent as text as a number', function () {
    patchJson(PROFILE_ENDPOINT, ['ajustes' => ['margen_botella' => '7500', 'tamanos_decant_ml' => ['3', '8'], 'tipo_cambio_usd' => '512.5']])
        ->assertOk()
        ->assertJsonPath('data.ajustes.margen_botella', 7500)
        ->assertJsonPath('data.ajustes.tamanos_decant_ml', [3, 8])
        ->assertJsonPath('data.ajustes.tipo_cambio_usd', 512.5);

    expect(storedSettingsOf(TwoBusinesses::FIRST))->toEqual(['bottle_margin_colones' => 7500, 'decant_sizes_ml' => [3, 8], 'usd_exchange_rate' => 512.5]);
});

it('rejects each invalid field in spanish and changes nothing', function (array $payload, string $field, string $message) {
    $before = DB::table('businesses')->where('slug', TwoBusinesses::FIRST)->first();

    $errors = fieldErrorsOf(patchJson(PROFILE_ENDPOINT, $payload));

    expect($errors)->toHaveKey($field)
        ->and($errors[$field][0])->toBe($message)
        ->and(DB::table('businesses')->where('slug', TwoBusinesses::FIRST)->first())->toEqual($before);
})->with([
    'empty name' => [['nombre' => ''], 'nombre', 'El campo nombre es obligatorio.'],
    'unknown timezone' => [['zona_horaria' => 'America/Atlantis'], 'zona_horaria', 'El campo zona horaria debe ser una zona horaria válida.'],
    'negative margin' => [['ajustes' => ['margen_botella' => -1]], 'ajustes.margen_botella', 'El campo margen por botella debe ser al menos 0.'],
    'margin with decimals' => [['ajustes' => ['margen_botella' => 5000.5]], 'ajustes.margen_botella', 'El campo margen por botella debe ser un número entero.'],
    'no decant sizes' => [['ajustes' => ['tamanos_decant_ml' => []]], 'ajustes.tamanos_decant_ml', 'El campo tamaños de decant debe tener al menos 1 elementos.'],
    'repeated decant size' => [['ajustes' => ['tamanos_decant_ml' => [5, 5]]], 'ajustes.tamanos_decant_ml.0', 'El campo tamaño de decant tiene un valor duplicado.'],
    'zero decant size' => [['ajustes' => ['tamanos_decant_ml' => [0, 5]]], 'ajustes.tamanos_decant_ml.0', 'El campo tamaño de decant debe ser al menos 1.'],
    'negative notice' => [['ajustes' => ['anticipacion_minima_horas' => -2]], 'ajustes.anticipacion_minima_horas', 'El campo anticipación mínima debe ser al menos 0.'],
    'notice in text' => [['ajustes' => ['anticipacion_minima_horas' => 'doce']], 'ajustes.anticipacion_minima_horas', 'El campo anticipación mínima debe ser un número entero.'],
    'negative change cutoff' => [['ajustes' => ['plazo_cambio_cita_horas' => -1]], 'ajustes.plazo_cambio_cita_horas', 'El campo plazo para cambiar la cita debe ser al menos 0.'],
    'negative waitlist hold' => [['ajustes' => ['apartado_lista_espera_horas' => -1]], 'ajustes.apartado_lista_espera_horas', 'El campo apartado de la lista de espera debe ser al menos 0.'],
    'unpaid expiry with decimals' => [['ajustes' => ['vencimiento_sin_pago_minutos' => 2.5]], 'ajustes.vencimiento_sin_pago_minutos', 'El campo vencimiento sin pago debe ser un número entero.'],
    'negative reminder' => [['ajustes' => ['recordatorios_horas' => [24, -1]]], 'ajustes.recordatorios_horas.1', 'El campo recordatorio debe ser al menos 0.'],
    'reminders as text' => [['ajustes' => ['recordatorios_horas' => '24']], 'ajustes.recordatorios_horas', 'El campo recordatorios debe ser un arreglo.'],
    'horizon shorter than the notice sent' => [['ajustes' => ['anticipacion_minima_horas' => 72, 'horizonte_reserva_dias' => 2]], 'ajustes.horizonte_reserva_dias', 'El horizonte de reserva no puede ser menor que la anticipación mínima.'],
    'horizon shorter than the stored notice' => [['ajustes' => ['horizonte_reserva_dias' => 0]], 'ajustes.horizonte_reserva_dias', 'El horizonte de reserva no puede ser menor que la anticipación mínima.'],
    'notice beyond the stored horizon' => [['ajustes' => ['anticipacion_minima_horas' => 721]], 'ajustes.anticipacion_minima_horas', 'El horizonte de reserva no puede ser menor que la anticipación mínima.'],
    'unknown gateway' => [['ajustes' => ['pasarela_por_defecto' => 'stripe']], 'ajustes.pasarela_por_defecto', 'El valor seleccionado para pasarela por defecto no es válido.'],
    'zero exchange rate' => [['ajustes' => ['tipo_cambio_usd' => 0]], 'ajustes.tipo_cambio_usd', 'El campo tipo de cambio debe ser mayor que 0.'],
    'negative exchange rate' => [['ajustes' => ['tipo_cambio_usd' => -510]], 'ajustes.tipo_cambio_usd', 'El campo tipo de cambio debe ser mayor que 0.'],
]);

it('ignores a business_id in the body and changes only the business of the session', function () {
    $other = TwoBusinesses::idOf(TwoBusinesses::SECOND);
    $otherBefore = DB::table('businesses')->where('id', $other)->first();

    patchJson(PROFILE_ENDPOINT, ['business_id' => $other, 'id' => $other, 'nombre' => 'Nombre nuevo'])
        ->assertOk()
        ->assertJsonPath('data.id', TwoBusinesses::idOf(TwoBusinesses::FIRST));

    expect(DB::table('businesses')->where('slug', TwoBusinesses::FIRST)->value('name'))->toBe('Nombre nuevo')
        ->and(DB::table('businesses')->where('id', $other)->first())->toEqual($otherBefore);
});

it('lets the owner of the other business see and change only their own', function () {
    $firstBefore = DB::table('businesses')->where('slug', TwoBusinesses::FIRST)->first();
    TwoBusinesses::actAsOwnerOf(TwoBusinesses::SECOND);

    getJson(PROFILE_ENDPOINT)->assertOk()->assertJsonPath('data.slug', TwoBusinesses::SECOND);
    patchJson(PROFILE_ENDPOINT, ['ajustes' => ['pasarela_por_defecto' => 'paypal']])
        ->assertOk()
        ->assertJsonPath('data.slug', TwoBusinesses::SECOND);

    expect(storedSettingsOf(TwoBusinesses::SECOND))->toBe(['default_gateway' => 'paypal'])
        ->and(DB::table('businesses')->where('slug', TwoBusinesses::FIRST)->first())->toEqual($firstBefore);
});

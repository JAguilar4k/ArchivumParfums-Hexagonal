<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Accounts\DemoAccounts;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

pest()->use(RefreshDatabaseAsOwner::class);

/*
 * The businesses of the whole platform, for its administrator (#347): the list, a new business with its owner, and
 * the isolation that the new owner gets from the first request.
 */

beforeEach(function () {
    DemoAccounts::seed();
    DemoAccounts::seedPlatformAdmin();
});

/**
 * A valid new business and its owner, overriding only what a scenario changes.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function platformBusinessPayload(array $overrides = []): array
{
    return [
        'nombre' => 'Aromas del Valle',
        'slug' => 'aromas-del-valle',
        'nombre_dueno' => 'Laura Jiménez Castro',
        'correo_dueno' => 'laura@aromasdelvalle.test',
        'contrasena_dueno' => 'clave-segura',
        ...$overrides,
    ];
}

it('lists every business of the platform by name, without settings, staff or customers', function () {
    getJson('/api/v1/plataforma/tiendas', DemoAccounts::signIn(DemoAccounts::PLATFORM_ADMIN))
        ->assertOk()
        ->assertExactJson(['data' => [
            ['id' => '0192f0c4-0000-7000-8000-000000000001', 'nombre' => 'ArchivumParfums', 'slug' => 'archivumparfums', 'zona_horaria' => 'America/Costa_Rica'],
            ['id' => '0192f0c4-0000-7000-8000-000000000002', 'nombre' => 'Esencias del Norte', 'slug' => 'esencias-del-norte', 'zona_horaria' => 'America/Costa_Rica'],
        ]]);
});

it('registers a business with the default settings and its owner', function () {
    $response = postJson('/api/v1/plataforma/tiendas', platformBusinessPayload(['lugar_cita' => 'Liberia, frente al parque']), DemoAccounts::signIn(DemoAccounts::PLATFORM_ADMIN))
        ->assertCreated()
        ->assertJsonPath('data.nombre', 'Aromas del Valle')
        ->assertJsonPath('data.slug', 'aromas-del-valle')
        ->assertJsonPath('data.zona_horaria', 'America/Costa_Rica');

    $business = BusinessModel::query()->where('slug', 'aromas-del-valle')->sole();
    $member = app(BusinessTransaction::class)->run($business->id, fn (): object => DB::table('members')
        ->join('users', 'users.id', '=', 'members.user_id')
        ->where('users.email', 'laura@aromasdelvalle.test')
        ->sole(['members.business_id', 'members.role', 'members.attends_appointments', 'users.is_platform_admin']));

    expect($response->json('data.id'))->toBe($business->id)
        ->and($business->settings)->toBe([])
        ->and($business->location_text)->toBe('Liberia, frente al parque')
        ->and($member->business_id)->toBe($business->id)
        ->and($member->role)->toBe('owner')
        ->and($member->attends_appointments)->toBeFalse()
        ->and($member->is_platform_admin)->toBeFalse();
});

it('leaves in the log of the new business that the administrator of the platform registered it', function () {
    postJson('/api/v1/plataforma/tiendas', platformBusinessPayload(), DemoAccounts::signIn(DemoAccounts::PLATFORM_ADMIN))->assertCreated();

    $business = BusinessModel::query()->where('slug', 'aromas-del-valle')->sole();
    $admin = DB::table('users')->where('email', DemoAccounts::PLATFORM_ADMIN)->value('id');
    // sole() also proves that registering the business left one entry and no other.
    $entry = app(BusinessTransaction::class)->run($business->id, fn (): object => DB::table('audit_logs')->sole());

    expect($entry->action)->toBe('business.registered')
        ->and($entry->resource_id)->toBe($business->id)
        ->and($entry->actor_user_id)->toBe($admin)
        ->and(json_decode((string) $entry->after, true))->toBe(['name' => 'Aromas del Valle', 'slug' => 'aromas-del-valle', 'timezone' => 'America/Costa_Rica']);
});

it('lets the new owner sign in and see only the new business in the panel', function () {
    postJson('/api/v1/plataforma/tiendas', platformBusinessPayload(), DemoAccounts::signIn(DemoAccounts::PLATFORM_ADMIN))->assertCreated();
    Auth::forgetGuards();

    $token = postJson('/api/v1/sesiones', ['correo' => 'laura@aromasdelvalle.test', 'contrasena' => 'clave-segura', 'dispositivo' => 'Computadora'])
        ->assertCreated()
        ->json('data.token');
    $headers = ['Authorization' => "Bearer {$token}"];

    getJson('/api/v1/cuenta', $headers)->assertOk()
        ->assertJsonPath('data.rol', 'dueno')
        ->assertJsonPath('data.tienda.slug', 'aromas-del-valle');
    getJson('/api/v1/panel/tienda', $headers)->assertOk()->assertJsonPath('data.slug', 'aromas-del-valle');
    getJson('/api/v1/panel/personal', $headers)->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.correo', 'laura@aromasdelvalle.test');
    getJson('/api/v1/panel/perfumes', $headers)->assertOk()->assertJsonCount(0, 'data');
});

it('answers 422 in the field to a slug or an owner email already in use, and creates nothing', function (array $overrides, string $field, string $message) {
    $before = [DB::table('businesses')->count(), DB::table('users')->count()];

    postJson('/api/v1/plataforma/tiendas', platformBusinessPayload($overrides), DemoAccounts::signIn(DemoAccounts::PLATFORM_ADMIN))
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/datos-invalidos')
        ->assertJsonPath("errores.{$field}.0", $message);

    expect([DB::table('businesses')->count(), DB::table('users')->count()])->toBe($before);
})->with([
    'slug of ArchivumParfums' => [['slug' => 'archivumparfums'], 'slug', 'Ya existe una tienda con ese slug.'],
    'email of a customer' => [['correo_dueno' => 'MARIANA@clientes.test'], 'correo_dueno', 'Ya existe una cuenta con ese correo.'],
    'email of the administrator' => [['correo_dueno' => DemoAccounts::PLATFORM_ADMIN], 'correo_dueno', 'Ya existe una cuenta con ese correo.'],
]);

it('answers 422 with every invalid field and creates nothing', function (array $overrides, string $field) {
    postJson('/api/v1/plataforma/tiendas', platformBusinessPayload($overrides), DemoAccounts::signIn(DemoAccounts::PLATFORM_ADMIN))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field], 'errores');

    expect(DB::table('businesses')->count())->toBe(2);
})->with([
    'missing name' => [['nombre' => ''], 'nombre'],
    'slug with uppercase letters' => [['slug' => 'Aromas'], 'slug'],
    'unknown time zone' => [['zona_horaria' => 'America/Atlantida'], 'zona_horaria'],
    'malformed owner email' => [['correo_dueno' => 'laura.aromas'], 'correo_dueno'],
    'short owner password' => [['contrasena_dueno' => '1234567'], 'contrasena_dueno'],
]);

it('ignores a business id or a platform mark in the body', function () {
    postJson('/api/v1/plataforma/tiendas', platformBusinessPayload(['id' => '0192f0c4-0000-7000-8000-000000000001', 'is_platform_admin' => true]), DemoAccounts::signIn(DemoAccounts::PLATFORM_ADMIN))
        ->assertCreated();

    expect(DB::table('users')->where('email', 'laura@aromasdelvalle.test')->value('is_platform_admin'))->toBeFalse()
        ->and(DB::table('businesses')->count())->toBe(3);
});

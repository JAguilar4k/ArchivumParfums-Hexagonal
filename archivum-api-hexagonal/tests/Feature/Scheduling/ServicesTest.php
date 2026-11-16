<?php

declare(strict_types=1);

use Database\Seeders\BusinessesSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Scheduling\Services\Infrastructure\Persistence\Models\ServiceModel;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

uses(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

const SERVICES_ENDPOINT = '/api/v1/panel/servicios';

beforeEach(function (): void {
    config()->set('archivum.demo_password', 'password');

    TwoBusinesses::create();
});

/**
 * Payload of a valid new service.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function servicePayload(array $overrides = []): array
{
    return [
        'nombre' => 'Prueba de olores',
        'duracion_minutos' => 45,
        'precio' => 0,
        'margen_minutos' => 15,
        'maximo_perfumes' => 5,
        ...$overrides,
    ];
}

/** Sign in the owner of the first business of the two of the tests. */
function ownerOfFirstBusiness(): UserModel
{
    return TwoBusinesses::actAsOwnerOf(TwoBusinesses::FIRST);
}

/** Insert a service of a business the queries are scoped to, as the panel would have written it. */
function insertServiceOf(string $businessId, string $name = 'Prueba de olores', bool $active = true): string
{
    $id = (string) Str::uuid7();

    DB::table('services')->insert([
        'id' => $id,
        'business_id' => $businessId,
        'name' => $name,
        'duration_minutes' => 45,
        'price' => 0,
        'buffer_minutes' => 15,
        'max_perfumes' => 5,
        'is_active' => $active,
    ]);

    return $id;
}

it('creates a service of the business of the session and answers 201 with its location', function (): void {
    ownerOfFirstBusiness();

    $response = postJson(SERVICES_ENDPOINT, servicePayload());

    $response->assertCreated()
        ->assertHeader('Content-Type', 'application/json');

    $id = (string) $response->json('data.id');

    expect($response->json('data'))->toMatchArray([
        'nombre' => 'Prueba de olores',
        'duracion_minutos' => 45,
        'precio' => 0,
        'margen_minutos' => 15,
        'maximo_perfumes' => 5,
        'activo' => true,
    ])->and($response->headers->get('Location'))->toEndWith(SERVICES_ENDPOINT.'/'.$id)
        // The identity is time ordered: the project stores UUID version 7.
        ->and($id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');

    $this->scopeQueriesTo(TwoBusinesses::idOf(TwoBusinesses::FIRST));

    expect(DB::table('services')->where('business_id', TwoBusinesses::idOf(TwoBusinesses::FIRST))->count())->toBe(1)
        ->and(DB::table('services')->where('business_id', TwoBusinesses::idOf(TwoBusinesses::SECOND))->count())->toBe(0);
});

it('answers 422 datos-invalidos as a problem document with the errors of each field', function (): void {
    ownerOfFirstBusiness();

    $response = postJson(SERVICES_ENDPOINT, []);

    $response->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json');

    expect($response->json('type'))->toEndWith('/problemas/datos-invalidos')
        ->and($response->json('status'))->toBe(422)
        ->and($response->json('instance'))->toBe('urn:uuid:'.$response->headers->get('X-Request-Id'))
        ->and($response->json('errores'))->toHaveKeys([
            'nombre', 'duracion_minutos', 'precio', 'margen_minutos', 'maximo_perfumes',
        ])
        ->and(DB::table('services')->count())->toBe(0);
});

it('rejects a duration or an amount that the integer columns cannot hold', function (): void {
    ownerOfFirstBusiness();

    $tooLarge = postJson(SERVICES_ENDPOINT, servicePayload(['precio' => 2147483648]));
    $tooLong = postJson(SERVICES_ENDPOINT, servicePayload(['duracion_minutos' => 2147483648]));
    $zero = postJson(SERVICES_ENDPOINT, servicePayload(['duracion_minutos' => 0]));

    expect($tooLarge->status())->toBe(422)
        ->and($tooLarge->json('errores'))->toHaveKey('precio')
        ->and($tooLong->status())->toBe(422)
        ->and($tooLong->json('errores'))->toHaveKey('duracion_minutos')
        ->and($zero->status())->toBe(422)
        ->and(DB::table('services')->count())->toBe(0);
});

it('edits only the fields the request sends', function (): void {
    ownerOfFirstBusiness();

    $id = (string) postJson(SERVICES_ENDPOINT, servicePayload())->json('data.id');

    $response = patchJson(SERVICES_ENDPOINT.'/'.$id, ['nombre' => 'Cata guiada', 'precio' => 25000]);

    $response->assertOk();

    expect($response->json('data'))->toMatchArray([
        'id' => $id,
        'nombre' => 'Cata guiada',
        'duracion_minutos' => 45,
        'precio' => 25000,
        'margen_minutos' => 15,
        'maximo_perfumes' => 5,
        'activo' => true,
    ]);
});

it('deactivates and reactivates the same service without deleting it', function (): void {
    ownerOfFirstBusiness();

    $id = (string) postJson(SERVICES_ENDPOINT, servicePayload())->json('data.id');

    $deactivated = patchJson(SERVICES_ENDPOINT.'/'.$id, ['activo' => false]);
    $deactivated->assertOk();

    $this->scopeQueriesTo(TwoBusinesses::idOf(TwoBusinesses::FIRST));

    expect($deactivated->json('data.activo'))->toBeFalse()
        ->and(DB::table('services')->where('id', $id)->value('is_active'))->toBeFalse()
        ->and(DB::table('services')->where('id', $id)->count())->toBe(1);

    $reactivated = patchJson(SERVICES_ENDPOINT.'/'.$id, ['activo' => true]);

    $reactivated->assertOk();

    expect($reactivated->json('data.activo'))->toBeTrue()
        ->and(DB::table('services')->where('id', $id)->value('is_active'))->toBeTrue();
});

it('lists the active and the inactive services of the session ordered by name', function (): void {
    $businessId = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($businessId);
    insertServiceOf($businessId, 'Cata guiada');
    insertServiceOf($businessId, 'Prueba de olores', active: false);

    $this->scopeQueriesTo(TwoBusinesses::idOf(TwoBusinesses::SECOND));
    insertServiceOf(TwoBusinesses::idOf(TwoBusinesses::SECOND), 'Servicio de la otra tienda');

    $this->scopeQueriesTo(null);
    ownerOfFirstBusiness();

    $response = getJson(SERVICES_ENDPOINT);

    $response->assertOk();

    expect(array_column($response->json('data'), 'nombre'))->toBe(['Cata guiada', 'Prueba de olores'])
        ->and(array_column($response->json('data'), 'activo'))->toBe([true, false]);
});

it('shows one service of the business of the session', function (): void {
    $businessId = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($businessId);
    $id = insertServiceOf($businessId, 'Cata guiada');

    $this->scopeQueriesTo(null);
    ownerOfFirstBusiness();

    $response = getJson(SERVICES_ENDPOINT.'/'.$id);

    $response->assertOk();

    expect($response->json('data'))->toMatchArray([
        'id' => $id,
        'nombre' => 'Cata guiada',
        'duracion_minutos' => 45,
        'precio' => 0,
        'margen_minutos' => 15,
        'maximo_perfumes' => 5,
        'activo' => true,
    ]);
});

it('answers 404 no-encontrado for a service of another business or an unknown one', function (): void {
    $this->scopeQueriesTo(TwoBusinesses::idOf(TwoBusinesses::SECOND));
    $ofAnotherBusiness = insertServiceOf(TwoBusinesses::idOf(TwoBusinesses::SECOND));

    $this->scopeQueriesTo(null);
    ownerOfFirstBusiness();

    $other = getJson(SERVICES_ENDPOINT.'/'.$ofAnotherBusiness);
    $unknown = getJson(SERVICES_ENDPOINT.'/'.'019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6f70');
    $editOther = patchJson(SERVICES_ENDPOINT.'/'.$ofAnotherBusiness, ['nombre' => 'Robado']);

    expect($other->status())->toBe(404)
        ->and($other->json('type'))->toEndWith('/problemas/no-encontrado')
        ->and($unknown->status())->toBe(404)
        ->and($editOther->status())->toBe(404)
        ->and($editOther->json('type'))->toEndWith('/problemas/no-encontrado');
});

it('forbids the staff and the customer, and refuses a request without a session', function (): void {
    TwoBusinesses::actAs(TwoBusinesses::ADVISOR);
    $asStaff = getJson(SERVICES_ENDPOINT);

    TwoBusinesses::actAs(TwoBusinesses::CUSTOMER);
    $asCustomer = getJson(SERVICES_ENDPOINT);

    Auth::forgetGuards();
    $asVisitor = getJson(SERVICES_ENDPOINT);

    expect($asStaff->status())->toBe(403)
        ->and($asStaff->json('type'))->toEndWith('/problemas/prohibido')
        ->and($asCustomer->status())->toBe(403)
        ->and($asVisitor->status())->toBe(401)
        ->and($asVisitor->json('type'))->toEndWith('/problemas/no-autenticado');
});

it('R11: seeds the initial service of every demo business', function (): void {
    seed(DatabaseSeeder::class);

    foreach ([BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID, BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID] as $businessId) {
        $this->scopeQueriesTo($businessId);

        $service = ServiceModel::query()->where('business_id', $businessId)->sole();

        expect($service->name)->toBe('Prueba de olores')
            ->and($service->duration_minutes)->toBe(45)
            ->and($service->price)->toBe(0)
            ->and($service->buffer_minutes)->toBe(15)
            ->and($service->max_perfumes)->toBe(5)
            ->and($service->is_active)->toBeTrue();
    }
});

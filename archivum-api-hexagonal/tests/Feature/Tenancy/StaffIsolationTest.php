<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Decants\Bottles\Infrastructure\Persistence\Models\DecantBottleModel;
use Src\Decants\Packs\Infrastructure\Persistence\Models\DecantPackModel;
use Src\Decants\Packs\Infrastructure\Persistence\Models\DecantPackPlanModel;
use Src\Sales\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Scheduling\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use Src\Scheduling\BusinessHours\Infrastructure\Persistence\Models\BusinessHoursModel;
use Src\Scheduling\ScheduleBlocks\Infrastructure\Persistence\Models\ScheduleBlockModel;
use Src\Scheduling\Services\Infrastructure\Persistence\Models\ServiceModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Exceptions\NotFoundException;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\Catalog\StoredPerfumes;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\getJson;
use function Pest\Laravel\json;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

const MISSING_RESOURCE = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99';

/**
 * Panel listings, each with the function that creates one of its rows in a business with its factory, which pins
 * that business, and returns its identifier. Every issue that adds a panel listing adds its row here.
 *
 * @return array<string, array{string, Closure(string): string}>
 */
function panelListings(): array
{
    return [
        'example: customers (test route)' => ['/api/v1/pruebas/panel/clientes', exampleCustomer(...)],
        'list the perfumes' => ['/api/v1/panel/perfumes', examplePerfume(...)],
        'list the bottles' => ['/api/v1/panel/botellas', exampleBottle(...)],
        'list the orders' => ['/api/v1/panel/pedidos', exampleOrder(...)],
        'list the services' => ['/api/v1/panel/servicios', exampleService(...)],
        'list the offers of decant packs' => ['/api/v1/panel/paquetes', examplePackPlan(...)],
        'list the sold decant packs' => ['/api/v1/panel/paquetes-vendidos', exampleSoldPack(...)],
        'list the blocks of the agenda' => ['/api/v1/panel/bloqueos?desde=2000-01-01&hasta=2100-01-01', exampleBlock(...)],
    ];
}

/**
 * Panel endpoints that read, change or delete one resource by its identifier: method, path without the
 * identifier, payload, table of the resource and the function that creates it in a business with its factory.
 * Every issue that adds one adds its row.
 *
 * @return array<string, array{string, string, array<string, mixed>, string, Closure(string): string}>
 */
function panelResourcesById(): array
{
    return [
        'example: show a customer (test route)' => ['GET', '/api/v1/pruebas/panel/clientes', [], 'customers', exampleCustomer(...)],
        'show a perfume' => ['GET', '/api/v1/panel/perfumes', [], 'perfumes', examplePerfume(...)],
        'edit a perfume' => ['PATCH', '/api/v1/panel/perfumes', ['nombre' => 'Nombre nuevo', 'activo' => false], 'perfumes', examplePerfume(...)],
        'show a bottle' => ['GET', '/api/v1/panel/botellas', [], 'decant_bottles', exampleBottle(...)],
        'edit bottle notes' => ['PATCH', '/api/v1/panel/botellas', ['notas' => 'Revisión'], 'decant_bottles', exampleBottle(...)],
        'read bottle history' => ['GET', '/api/v1/panel/botellas/{id}/movimientos', [], 'decant_bottles', exampleBottle(...)],
        'adjust a bottle' => ['POST', '/api/v1/panel/botellas/{id}/ajustes', ['ml' => -1, 'motivo' => 'Conteo'], 'decant_bottles', exampleBottle(...)],
        'show an order' => ['GET', '/api/v1/panel/pedidos', [], 'orders', exampleOrder(...)],
        'prepare order decants' => ['POST', '/api/v1/panel/pedidos/{id}/preparacion', [], 'orders', exampleOrder(...)],
        'mark an order ready' => ['POST', '/api/v1/panel/pedidos/{id}/listo', [], 'orders', exampleOrder(...)],
        'deliver an order' => ['POST', '/api/v1/panel/pedidos/{id}/entrega', [], 'orders', exampleOrder(...)],
        'record a payment on delivery' => ['POST', '/api/v1/panel/pedidos/{id}/pagos-manuales', ['metodo' => 'sinpe', 'monto' => 1000, 'referencia' => 'SINPE-2026-0001'], 'orders', exampleOrder(...)],
        'process order refunds' => ['POST', '/api/v1/panel/pedidos/{id}/reembolsos', [], 'orders', exampleOrder(...)],
        'mark a line unavailable' => ['POST', '/api/v1/panel/pedidos/{id}/lineas/'.MISSING_RESOURCE.'/no-disponible', [], 'orders', exampleOrder(...)],
        'show a service' => ['GET', '/api/v1/panel/servicios', [], 'services', exampleService(...)],
        'edit a service' => ['PATCH', '/api/v1/panel/servicios', ['nombre' => 'Nombre nuevo', 'activo' => false], 'services', exampleService(...)],
        'edit an offer of decant packs' => ['PATCH', '/api/v1/panel/paquetes', ['precio' => 1, 'activo' => false], 'decant_pack_plans', examplePackPlan(...)],
        'read the hours of a person' => ['GET', '/api/v1/panel/personal/{id}/horario', [], 'members', exampleStaffMember(...)],
        'replace the hours of a person' => ['PUT', '/api/v1/panel/personal/{id}/horario', ['tramos' => [['dia_semana' => 1, 'inicio' => '09:00', 'fin' => '10:00']]], 'members', exampleStaffMember(...)],
        'edit a block of the agenda' => ['PATCH', '/api/v1/panel/bloqueos', ['motivo' => 'Inventario'], 'schedule_blocks', exampleBlock(...)],
        'delete a block of the agenda' => ['DELETE', '/api/v1/panel/bloqueos', [], 'schedule_blocks', exampleBlock(...)],
        'reschedule an appointment of the store' => ['PATCH', '/api/v1/panel/citas', ['inicia_en' => '2026-12-01T10:00:00-06:00'], 'appointments', exampleAppointment(...)],
        'cancel an appointment of the store' => ['POST', '/api/v1/panel/citas/{id}/cancelacion', ['motivo' => 'La clienta avisó'], 'appointments', exampleAppointment(...)],
    ];
}

/**
 * Panel endpoints that act on the business of the session itself, with no identifier in the path: method, path
 * and payload. Every issue that adds one adds its row.
 *
 * @return array<string, array{string, string, array<string, mixed>}>
 */
function panelBusinessEndpoints(): array
{
    return [
        'read the profile of the business' => ['GET', '/api/v1/panel/tienda', []],
        'change the profile of the business' => ['PATCH', '/api/v1/panel/tienda', ['nombre' => 'Nombre nuevo', 'ajustes' => ['pasarela_por_defecto' => 'paypal']]],
    ];
}

/**
 * Panel endpoints that read or replace rows of the business of the session with no identifier in the path, such as its
 * opening hours: method, path, payload, table of the rows and the function that creates one of them in a business with
 * its factory. Every issue that adds one adds its row.
 *
 * @return array<string, array{string, string, array<string, mixed>, string, Closure(string): string}>
 */
function panelBusinessRows(): array
{
    return [
        'read the opening hours' => ['GET', '/api/v1/panel/horario', [], 'business_hours', exampleOpeningHours(...)],
        'replace the opening hours' => ['PUT', '/api/v1/panel/horario', ['tramos' => [['dia_semana' => 1, 'inicio' => '07:00', 'fin' => '08:00']]], 'business_hours', exampleOpeningHours(...)],
    ];
}

/**
 * Panel endpoints that create a resource in the business of the session: path, payload and the table of the resource.
 * Every issue that adds one adds its row.
 *
 * @return array<string, array{string, Closure(string): array<string, mixed>, string}>
 */
function panelCreations(): array
{
    return [
        'create a perfume' => ['/api/v1/panel/perfumes', fn (string $businessId): array => ['marca' => 'Tom Ford', 'nombre' => 'Oud Wood', 'concentracion' => 'EDP'], 'perfumes'],
        'create a bottle' => ['/api/v1/panel/botellas', function (string $businessId): array {
            $perfume = StoredPerfumes::create($businessId, variants: [[100, 15000, 15000]]);

            return ['presentacion_id' => $perfume->variants[0]->id->value, 'ml_restantes' => 100, 'abierta_en' => '2026-10-25T10:00:00-06:00'];
        }, 'decant_bottles'],
        'create a service' => ['/api/v1/panel/servicios', fn (string $businessId): array => [
            'nombre' => 'Cata guiada', 'duracion_minutos' => 30, 'precio' => 0, 'margen_minutos' => 10, 'maximo_perfumes' => 3,
        ], 'services'],
        'define an offer of decant packs' => ['/api/v1/panel/paquetes', fn (string $businessId): array => [
            'nombre' => 'Descubrimiento', 'cantidad_decants' => 5, 'ml_por_decant' => 5, 'precio' => 40000, 'vigencia_dias' => 180,
        ], 'decant_pack_plans'],
        'create a block of the agenda' => ['/api/v1/panel/bloqueos', fn (string $businessId): array => [
            'inicia_en' => '2026-12-01T09:00:00-06:00', 'termina_en' => '2026-12-01T10:00:00-06:00', 'motivo' => 'Inventario',
        ], 'schedule_blocks'],
    ];
}

/** Create a perfume in a business, which pins that business, and return its identifier. */
function examplePerfume(string $businessId): string
{
    return StoredPerfumes::create($businessId)->id->value;
}

/** Create a customer with its own account in a business and return the customer identifier. */
function exampleCustomer(string $businessId): string
{
    return CustomerModel::factory()->create(['business_id' => $businessId])->id;
}

function exampleBottle(string $businessId): string
{
    return DecantBottleModel::factory()->create(['business_id' => $businessId])->id;
}

function exampleOrder(string $businessId): string
{
    return OrderModel::factory()->create(['business_id' => $businessId])->id;
}

/** Create a service in a business with its factory, which pins that business, and return its identifier. */
function exampleService(string $businessId): string
{
    return ServiceModel::factory()->create(['business_id' => $businessId])->id;
}

function examplePackPlan(string $businessId): string
{
    return DecantPackPlanModel::factory()->create(['business_id' => $businessId])->id;
}

function exampleSoldPack(string $businessId): string
{
    return DecantPackModel::factory()->create(['business_id' => $businessId])->id;
}

/** Open a business one more weekday, the next one it does not open yet, with its factory, and return the identifier. */
function exampleOpeningHours(string $businessId): string
{
    app(BusinessTransaction::class)->scope($businessId);
    $weekday = DB::table('business_hours')->where('business_id', $businessId)->count() + 1;

    return BusinessHoursModel::factory()->create(['business_id' => $businessId, 'weekday' => $weekday])->id;
}

/** Create a person of the staff who attends appointments in a business with its factory and return the identifier. */
function exampleStaffMember(string $businessId): string
{
    return MemberModel::factory()->staff()->create(['business_id' => $businessId, 'attends_appointments' => true])->id;
}

/** Create a block of the whole business with its factory and return its identifier. */
function exampleBlock(string $businessId): string
{
    return ScheduleBlockModel::factory()->create(['business_id' => $businessId])->id;
}

/** Create an appointment with its factory, which also creates its service, customer and person in the business. */
function exampleAppointment(string $businessId): string
{
    return AppointmentModel::factory()->create(['business_id' => $businessId])->id;
}

function panelResourcePath(string $path, string $id): string
{
    return str_contains($path, '{id}') ? str_replace('{id}', $id, $path) : "{$path}/{$id}";
}

beforeEach(function () {
    TwoBusinesses::create();

    // An example of the rule until the first panel endpoint with resources exists: every query filters by the business of BusinessContext.
    // Like the other throwaway routes, it lives under api/v1/pruebas, outside the prefixes that the route scans walk.
    Route::prefix('api/v1/pruebas/panel/clientes')->middleware(['api', 'auth:sanctum', 'business:staff'])->group(function (): void {
        Route::get('/', fn (BusinessContext $context) => ['data' => DB::table('customers')
            ->where('business_id', $context->current()->value)
            ->get(['id'])]);
        Route::get('{cliente}', fn (BusinessContext $context, string $cliente) => ['data' => DB::table('customers')
            ->where('business_id', $context->current()->value)
            ->where('id', $cliente)
            ->first(['id']) ?? throw new class('problems.no-encontrado.detail') extends NotFoundException {}]);
    });
});

it('lists the rows of the business of the session and none of the other, for the owner of each one', function (string $path, Closure $create) {
    foreach ([TwoBusinesses::FIRST => TwoBusinesses::SECOND, TwoBusinesses::SECOND => TwoBusinesses::FIRST] as $own => $other) {
        $ownRow = $create(TwoBusinesses::idOf($own));
        $foreignRow = $create(TwoBusinesses::idOf($other));
        TwoBusinesses::actAsOwnerOf($own);

        $listed = getJson($path)->assertOk()->json('data.*.id');

        expect($listed)->toContain($ownRow)
            ->and(in_array($foreignRow, $listed, true))->toBeFalse();
    }
})->with(panelListings());

it('answers a resource of the other business exactly like one that does not exist and leaves it as it was, for the owner of each one', function (string $method, string $path, array $payload, string $table, Closure $create) {
    foreach ([TwoBusinesses::FIRST => TwoBusinesses::SECOND, TwoBusinesses::SECOND => TwoBusinesses::FIRST] as $own => $other) {
        $foreign = $create(TwoBusinesses::idOf($other));
        $this->scopeQueriesTo(TwoBusinesses::idOf($other));
        $before = DB::table($table)->where('id', $foreign)->first();
        TwoBusinesses::actAsOwnerOf($own);

        $foreignResponse = json($method, panelResourcePath($path, $foreign), $payload)->assertNotFound();
        $missingResponse = json($method, panelResourcePath($path, MISSING_RESOURCE), $payload)->assertNotFound();
        $this->scopeQueriesTo(TwoBusinesses::idOf($other));

        expect(Arr::except($foreignResponse->json(), 'instance'))->toBe(Arr::except($missingResponse->json(), 'instance'))
            ->and(DB::table($table)->where('id', $foreign)->first())->toEqual($before);
    }
})->with(panelResourcesById());

it('acts on the business of the session and leaves the other as it was, for the owner of each one', function (string $method, string $path, array $payload) {
    foreach ([TwoBusinesses::FIRST => TwoBusinesses::SECOND, TwoBusinesses::SECOND => TwoBusinesses::FIRST] as $own => $other) {
        $before = DB::table('businesses')->where('id', TwoBusinesses::idOf($other))->first();
        TwoBusinesses::actAsOwnerOf($own);

        json($method, $path, $payload)
            ->assertOk()
            ->assertJsonPath('data.id', TwoBusinesses::idOf($own));

        expect(DB::table('businesses')->where('id', TwoBusinesses::idOf($other))->first())->toEqual($before);
    }
})->with(panelBusinessEndpoints());

it('answers the rows of the business of the session the same whatever the other has, and leaves those of the other as they were, for the owner of each one', function (string $method, string $path, array $payload, string $table, Closure $create) {
    foreach ([TwoBusinesses::FIRST => TwoBusinesses::SECOND, TwoBusinesses::SECOND => TwoBusinesses::FIRST] as $own => $other) {
        $create(TwoBusinesses::idOf($own));
        TwoBusinesses::actAsOwnerOf($own);
        $alone = json($method, $path, $payload)->assertOk()->json();

        $create(TwoBusinesses::idOf($other));
        $this->scopeQueriesTo(TwoBusinesses::idOf($other));
        $before = DB::table($table)->orderBy('id')->get();
        TwoBusinesses::actAsOwnerOf($own);

        expect(json($method, $path, $payload)->assertOk()->json())->toEqual($alone);

        $this->scopeQueriesTo(TwoBusinesses::idOf($other));
        expect(DB::table($table)->orderBy('id')->get())->toEqual($before);
    }
})->with(panelBusinessRows());

it('creates the resource in the business of the session, whatever the body says, and leaves the other as it was, for the owner of each one', function (string $path, Closure $payload, string $table) {
    foreach ([TwoBusinesses::FIRST => TwoBusinesses::SECOND, TwoBusinesses::SECOND => TwoBusinesses::FIRST] as $own => $other) {
        $this->scopeQueriesTo(TwoBusinesses::idOf($other));
        $before = DB::table($table)->count();
        TwoBusinesses::actAsOwnerOf($own);
        $body = $payload(TwoBusinesses::idOf($own));

        $created = json('POST', $path, [
            ...$body,
            'business_id' => TwoBusinesses::idOf($other),
            'tienda_id' => TwoBusinesses::idOf($other),
        ])->assertCreated()->json('data.id');

        $this->scopeQueriesTo(TwoBusinesses::idOf($own));
        expect(DB::table($table)->where('id', $created)->value('business_id'))->toBe(TwoBusinesses::idOf($own));

        $this->scopeQueriesTo(TwoBusinesses::idOf($other));
        expect(DB::table($table)->count())->toBe($before);
    }
})->with(panelCreations());

it('lets each person of the two businesses act with their own role and business', function (string $email, string $role, ?string $slug) {
    TwoBusinesses::actAs($email);

    getJson('/api/v1/cuenta')
        ->assertOk()
        ->assertJsonPath('data.rol', $role)
        ->assertJsonPath('data.tienda.id', $slug === null ? null : TwoBusinesses::idOf($slug));
})->with([
    'owner of the first business' => [TwoBusinesses::OWNERS[TwoBusinesses::FIRST], 'dueno', TwoBusinesses::FIRST],
    'advisor of the first business' => [TwoBusinesses::ADVISOR, 'colaborador', TwoBusinesses::FIRST],
    'owner of the second business' => [TwoBusinesses::OWNERS[TwoBusinesses::SECOND], 'dueno', TwoBusinesses::SECOND],
    'customer of the first business' => [TwoBusinesses::CUSTOMER, 'cliente', null],
    'customer of both businesses' => [TwoBusinesses::CUSTOMER_OF_BOTH, 'cliente', null],
]);

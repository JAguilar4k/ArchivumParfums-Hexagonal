<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Decants\Bottles\Infrastructure\Persistence\Models\DecantBottleModel;
use Src\Sales\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Exceptions\NotFoundException;
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
        'process order refunds' => ['POST', '/api/v1/panel/pedidos/{id}/reembolsos', [], 'orders', exampleOrder(...)],
        'mark a line unavailable' => ['POST', '/api/v1/panel/pedidos/{id}/lineas/'.MISSING_RESOURCE.'/no-disponible', [], 'orders', exampleOrder(...)],
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

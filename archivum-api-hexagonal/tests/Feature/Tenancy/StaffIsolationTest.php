<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Exceptions\NotFoundException;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\getJson;
use function Pest\Laravel\json;

pest()->use(RefreshDatabaseAsOwner::class);

const MISSING_RESOURCE = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99';

/**
 * Panel listings, each with the function that creates one of its rows in a business and returns its identifier.
 * Every issue that adds a panel listing adds its row here.
 *
 * @return array<string, array{string, Closure(string): string}>
 */
function panelListings(): array
{
    return [
        'example: customers (test route)' => ['/api/v1/pruebas/panel/clientes', exampleCustomer(...)],
    ];
}

/**
 * Panel endpoints that read, change or delete one resource by its identifier: method, path without the
 * identifier, payload, table of the resource and the function that creates it in a business.
 * Every issue that adds one adds its row.
 *
 * @return array<string, array{string, string, array<string, mixed>, string, Closure(string): string}>
 */
function panelResourcesById(): array
{
    return [
        'example: show a customer (test route)' => ['GET', '/api/v1/pruebas/panel/clientes', [], 'customers', exampleCustomer(...)],
    ];
}

/** Create a customer with its own account in a business and return the customer identifier. */
function exampleCustomer(string $businessId): string
{
    $userId = (string) DB::table('users')->insertGetId([
        'name' => 'Cliente de prueba',
        'email' => Str::uuid7().'@clientes.test',
        'password' => 'hash',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return (string) DB::table('customers')->insertGetId([
        'business_id' => $businessId,
        'user_id' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Create one row with the given function inside a business and return its identifier.
 *
 * @param  Closure(string): string  $create
 */
function rowOf(string $slug, Closure $create): string
{
    $businessId = TwoBusinesses::idOf($slug);
    TwoBusinesses::scopeQueriesTo($businessId);

    return $create($businessId);
}

beforeEach(function () {
    TwoBusinesses::seed();

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
        $ownRow = rowOf($own, $create);
        $foreignRow = rowOf($other, $create);
        TwoBusinesses::actAsOwnerOf($own);

        $listed = getJson($path)->assertOk()->json('data.*.id');

        expect($listed)->toContain($ownRow)
            ->and(in_array($foreignRow, $listed, true))->toBeFalse();
    }
})->with(panelListings());

it('answers a resource of the other business exactly like one that does not exist and leaves it as it was, for the owner of each one', function (string $method, string $path, array $payload, string $table, Closure $create) {
    foreach ([TwoBusinesses::FIRST => TwoBusinesses::SECOND, TwoBusinesses::SECOND => TwoBusinesses::FIRST] as $own => $other) {
        $foreign = rowOf($other, $create);
        $before = DB::table($table)->where('id', $foreign)->first();
        TwoBusinesses::actAsOwnerOf($own);

        $foreignResponse = json($method, "{$path}/{$foreign}", $payload)->assertNotFound();
        $missingResponse = json($method, "{$path}/".MISSING_RESOURCE, $payload)->assertNotFound();
        TwoBusinesses::scopeQueriesTo(TwoBusinesses::idOf($other));

        expect(Arr::except($foreignResponse->json(), 'instance'))->toBe(Arr::except($missingResponse->json(), 'instance'))
            ->and(DB::table($table)->where('id', $foreign)->first())->toEqual($before);
    }
})->with(panelResourcesById());

it('lets each person of the demo act on their own business', function (string $email, string $slug) {
    TwoBusinesses::actAs($email);

    getJson('/api/v1/cuenta')->assertOk()->assertJsonPath('data.tienda.id', TwoBusinesses::idOf($slug));
})->with([
    'owner of the first business' => [TwoBusinesses::OWNERS[TwoBusinesses::FIRST], TwoBusinesses::FIRST],
    'advisor of the first business' => [TwoBusinesses::ADVISOR, TwoBusinesses::FIRST],
    'owner of the second business' => [TwoBusinesses::OWNERS[TwoBusinesses::SECOND], TwoBusinesses::SECOND],
]);

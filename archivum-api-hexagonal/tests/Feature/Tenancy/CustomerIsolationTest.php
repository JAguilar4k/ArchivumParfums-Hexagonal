<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Src\Accounts\Users\Infrastructure\Tenancy\CustomerBusinessContext;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\getJson;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

const MISSING_CUSTOMER_RESOURCE = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d97';

/**
 * Identifiers of the customer records of an account in each of the two businesses where it has one, by slug,
 * read with each business pinned in turn.
 *
 * @param  Closure(?string): void  $scopeQueriesTo
 * @return array<string, string>
 */
function customerRecordsOf(Closure $scopeQueriesTo, string $email): array
{
    $records = [];

    foreach ([TwoBusinesses::FIRST, TwoBusinesses::SECOND] as $slug) {
        $businessId = TwoBusinesses::idOf($slug);
        $scopeQueriesTo($businessId);
        $id = DB::table('customers')
            ->where('business_id', $businessId)
            ->where('user_id', TwoBusinesses::userIdOf($email))
            ->value('id');

        if (is_string($id)) {
            $records[$slug] = $id;
        }
    }

    return $records;
}

beforeEach(function () {
    TwoBusinesses::create();

    // An example of the routes of /api/v1/cuenta until the first resource of a customer exists (#59): a listing
    // that reads one business at a time and a resource whose business comes from fn_customer_resource_business.
    // Like the other throwaway routes, it lives under api/v1/pruebas, outside the prefixes that the route scans walk.
    Route::prefix('api/v1/pruebas/cuenta/clientes')->middleware(['api', 'auth:sanctum', 'role:customer'])->group(function (): void {
        Route::get('/', fn (Request $request, CustomerBusinessContext $context) => ['data' => Arr::flatten(
            $context->eachBusiness(fn (BusinessId $businessId): array => DB::table('customers')
                ->where('business_id', $businessId->value)
                ->where('user_id', $request->user()?->getAuthIdentifier())
                ->get(['id', 'business_id'])
                ->map(fn (object $row): array => [
                    'id' => $row->id,
                    'tienda' => $row->business_id,
                    'tienda_fijada' => DB::scalar("select current_setting('app.business_id', true)"),
                ])
                ->all()),
            1,
        )]);
        Route::get('{cliente}', fn (BusinessContext $context, string $cliente) => ['data' => [
            'id' => DB::table('customers')->where('business_id', $context->current()->value)->where('id', $cliente)->value('id'),
            'tienda' => $context->current()->value,
            'tienda_fijada' => DB::scalar("select current_setting('app.business_id', true)"),
        ]])->middleware('business:account');
    });
});

it('lists the resources of a customer in every business where it is one, pinning each business in turn', function () {
    $records = customerRecordsOf($this->scopeQueriesTo(...), TwoBusinesses::CUSTOMER_OF_BOTH);
    TwoBusinesses::actAs(TwoBusinesses::CUSTOMER_OF_BOTH);

    $listed = getJson('/api/v1/pruebas/cuenta/clientes')->assertOk()->json('data');

    expect(array_column($listed, 'id', 'tienda'))->toEqual([
        TwoBusinesses::idOf(TwoBusinesses::FIRST) => $records[TwoBusinesses::FIRST],
        TwoBusinesses::idOf(TwoBusinesses::SECOND) => $records[TwoBusinesses::SECOND],
    ]);

    foreach ($listed as $row) {
        expect($row['tienda_fijada'])->toBe($row['tienda']);
    }
});

it('lists none of the resources of another customer', function () {
    $foreign = array_values(customerRecordsOf($this->scopeQueriesTo(...), TwoBusinesses::CUSTOMER_OF_BOTH));
    $own = array_values(customerRecordsOf($this->scopeQueriesTo(...), TwoBusinesses::CUSTOMER));
    TwoBusinesses::actAs(TwoBusinesses::CUSTOMER);

    $listed = getJson('/api/v1/pruebas/cuenta/clientes')->assertOk()->json('data.*.id');

    expect($listed)->toBe($own)
        ->and(array_intersect($listed, $foreign))->toBe([]);
});

it('pins the business of a resource of the customer, in each business', function (string $slug) {
    $record = customerRecordsOf($this->scopeQueriesTo(...), TwoBusinesses::CUSTOMER_OF_BOTH)[$slug];
    TwoBusinesses::actAs(TwoBusinesses::CUSTOMER_OF_BOTH);

    getJson("/api/v1/pruebas/cuenta/clientes/{$record}")->assertOk()->assertExactJson(['data' => [
        'id' => $record,
        'tienda' => TwoBusinesses::idOf($slug),
        'tienda_fijada' => TwoBusinesses::idOf($slug),
    ]]);
})->with([TwoBusinesses::FIRST, TwoBusinesses::SECOND]);

it('answers a resource of another customer exactly like one that does not exist or a malformed one', function () {
    $foreign = customerRecordsOf($this->scopeQueriesTo(...), TwoBusinesses::CUSTOMER_OF_BOTH)[TwoBusinesses::FIRST];
    TwoBusinesses::actAs(TwoBusinesses::CUSTOMER);

    $responses = array_map(
        fn (string $id): array => Arr::except(
            getJson("/api/v1/pruebas/cuenta/clientes/{$id}")->assertNotFound()->json(),
            'instance',
        ),
        [$foreign, MISSING_CUSTOMER_RESOURCE, 'no-es-un-identificador'],
    );

    expect($responses[0])->toBe($responses[1])
        ->and($responses[0])->toBe($responses[2])
        ->and($responses[0]['detail'])->toBe('El recurso solicitado no existe.');
});

it('asks for a token on the routes of the account', function (string $path) {
    getJson($path)->assertUnauthorized();
})->with([
    'listing' => '/api/v1/pruebas/cuenta/clientes',
    'resource' => '/api/v1/pruebas/cuenta/clientes/'.MISSING_CUSTOMER_RESOURCE,
]);

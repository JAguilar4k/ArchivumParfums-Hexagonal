<?php

declare(strict_types=1);

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Src\Accounts\Users\Domain\Exceptions\CustomerResourceNotFoundException;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Accounts\Users\Infrastructure\Tenancy\CustomerBusinessContext;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Infrastructure\Tenancy\ScopeRequestToBusiness;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

/** The adapter that the business:account middleware gives to a route of the account that names a customer record. */
function customerBusinessContext(string $customerId): BusinessContext
{
    $request = Request::create("/api/v1/pruebas/cuenta/clientes/{$customerId}");
    $route = (new Route('GET', 'api/v1/pruebas/cuenta/clientes/{cliente}', []))->bind($request);
    $request->setRouteResolver(fn (): Route => $route);
    app()->instance('request', $request);

    $context = app(ScopeRequestToBusiness::contextFor(ScopeRequestToBusiness::ACCOUNT));
    assert($context instanceof BusinessContext);

    return $context;
}

it('binds the account routes to the adapter that reads the resources of the customer', function () {
    expect(customerBusinessContext('0192f0c4-0000-7000-8000-000000000001'))->toBeInstanceOf(CustomerBusinessContext::class);
});

it('gives a customer the business of its own customer record', function () {
    $customer = CustomerModel::factory()->create();
    Sanctum::actingAs(UserModel::query()->whereKey($customer->user_id)->sole());

    expect(customerBusinessContext($customer->id)->current()->value)->toBe($customer->business_id);
});

it('answers the customer record of another account as not found', function () {
    $foreign = CustomerModel::factory()->create();
    Sanctum::actingAs(UserModel::factory()->create());

    customerBusinessContext($foreign->id)->current();
})->throws(CustomerResourceNotFoundException::class);

it('runs an operation once in each business where the account is a customer, with that business pinned', function () {
    $user = UserModel::factory()->create();
    $businesses = [
        CustomerModel::factory()->create(['user_id' => $user->id])->business_id,
        CustomerModel::factory()->create(['user_id' => $user->id])->business_id,
    ];
    sort($businesses);
    Sanctum::actingAs($user);
    $context = customerBusinessContext('0192f0c4-0000-7000-8000-000000000001');
    assert($context instanceof CustomerBusinessContext);

    $passes = $context->eachBusiness(fn (BusinessId $businessId): array => [
        'tienda' => $businessId->value,
        'actual' => $context->current()->value,
        'fijada' => DB::scalar("select current_setting('app.business_id', true)"),
    ]);

    expect($passes)->toBe(array_map(
        fn (string $businessId): array => ['tienda' => $businessId, 'actual' => $businessId, 'fijada' => $businessId],
        $businesses,
    ));
});

it('has no business to give without an authenticated person', function () {
    customerBusinessContext('0192f0c4-0000-7000-8000-000000000001')->current();
})->throws(AuthenticationException::class);

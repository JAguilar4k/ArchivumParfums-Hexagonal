<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Src\Accounts\Users\Domain\Contracts\CustomerBusinesses;
use Src\Accounts\Users\Domain\ValueObjects\CustomerResourceType;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Domain\Contracts\MemberRepository;
use Src\Businesses\Members\Domain\Entities\Member;
use Src\Businesses\Members\Domain\ValueObjects\MemberRole;
use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Businesses\Profile\Domain\Entities\Business;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessName;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSlug;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessTimezone;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\MemberId;
use Src\Shared\Domain\ValueObjects\UserId;
use Src\Shared\Infrastructure\Tenancy\UnresolvedBusinessContext;
use Tests\Support\Accounts\InMemoryCustomerBusinesses;
use Tests\Support\Businesses\InMemoryBusinessRepository;
use Tests\Support\Businesses\InMemoryMemberRepository;

use function Pest\Laravel\getJson;

const SCOPED_BUSINESS_ID = '0192f0c4-0000-7000-8000-0000000000aa';

const SCOPED_ACCOUNT_ID = '0192f0c4-0000-7000-8000-0000000000bb';

const SCOPED_CUSTOMER_ID = '0192f0c4-0000-7000-8000-0000000000dd';

/** Whether the closure of a test route ran, which stands for reaching a use case. */
function reachedScopedRoute(?bool $reached = null): bool
{
    static $value = false;

    if ($reached !== null) {
        $value = $reached;
    }

    return $value;
}

/**
 * Business that BusinessContext gives, business pinned in the transaction and how many transactions are open.
 *
 * @return array{tienda: string, fijada: string, transacciones: int}
 */
function requestScope(BusinessContext $context): array
{
    reachedScopedRoute(true);

    return [
        'tienda' => $context->current()->value,
        'fijada' => (string) DB::scalar("select current_setting('app.business_id', true)"),
        'transacciones' => DB::transactionLevel(),
    ];
}

beforeEach(function () {
    reachedScopedRoute(false);

    // The businesses, their staff and their customers live in memory, so these requests touch no row of the database.
    $businesses = new InMemoryBusinessRepository;
    $businesses->save(Business::create(
        new BusinessId(SCOPED_BUSINESS_ID),
        new BusinessName('Esencias del Norte'),
        new BusinessSlug('esencias-del-norte'),
        new BusinessTimezone(BusinessTimezone::DEFAULT),
        null,
    ));
    app()->instance(BusinessRepository::class, $businesses);

    $members = new InMemoryMemberRepository;
    $members->save(Member::create(
        new MemberId('0192f0c4-0000-7000-8000-0000000000cc'),
        new UserId(SCOPED_ACCOUNT_ID),
        new BusinessId(SCOPED_BUSINESS_ID),
        MemberRole::Owner,
    ));
    app()->instance(MemberRepository::class, $members);

    $customers = new InMemoryCustomerBusinesses;
    $customers->add(CustomerResourceType::Customer, SCOPED_CUSTOMER_ID, SCOPED_ACCOUNT_ID, new BusinessId(SCOPED_BUSINESS_ID));
    app()->instance(CustomerBusinesses::class, $customers);

    // Throwaway routes that report the scope of their request.
    Route::prefix('api/v1/pruebas')->middleware('api')->group(function (): void {
        Route::get('panel', fn (BusinessContext $context): array => requestScope($context))->middleware(['auth:sanctum', 'business:staff']);
        Route::get('tiendas/{tienda}', fn (BusinessContext $context): array => requestScope($context))->middleware('business:route');
        Route::get('cuenta/clientes/{cliente}', fn (BusinessContext $context): array => requestScope($context))->middleware(['auth:sanctum', 'business:account']);
    });
});

it('binds BusinessContext to an adapter that fails outside a route group with the business middleware', function () {
    $context = app(BusinessContext::class);

    expect($context)->toBeInstanceOf(UnresolvedBusinessContext::class)
        ->and(fn () => $context->current())->toThrow(LogicException::class);
});

it('runs a panel request in one transaction pinned to the business of the session', function () {
    Sanctum::actingAs(UserModel::factory()->make(['id' => SCOPED_ACCOUNT_ID]));

    getJson('/api/v1/pruebas/panel')
        ->assertOk()
        ->assertExactJson(['tienda' => SCOPED_BUSINESS_ID, 'fijada' => SCOPED_BUSINESS_ID, 'transacciones' => 1]);
});

it('runs a public request in one transaction pinned to the business of the slug of the URL', function () {
    getJson('/api/v1/pruebas/tiendas/esencias-del-norte')
        ->assertOk()
        ->assertExactJson(['tienda' => SCOPED_BUSINESS_ID, 'fijada' => SCOPED_BUSINESS_ID, 'transacciones' => 1]);
});

it('runs a request for a resource of the customer in one transaction pinned to the business of the resource', function () {
    Sanctum::actingAs(UserModel::factory()->make(['id' => SCOPED_ACCOUNT_ID]));

    getJson('/api/v1/pruebas/cuenta/clientes/'.SCOPED_CUSTOMER_ID)
        ->assertOk()
        ->assertExactJson(['tienda' => SCOPED_BUSINESS_ID, 'fijada' => SCOPED_BUSINESS_ID, 'transacciones' => 1]);
});

it('answers 404 to an unknown or malformed slug before reaching any use case', function (string $slug) {
    getJson("/api/v1/pruebas/tiendas/{$slug}")
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/no-encontrado')
        ->assertJsonPath('detail', 'La tienda no existe.');

    expect(reachedScopedRoute())->toBeFalse();
})->with(['unknown' => 'no-existe', 'malformed' => 'Esencias_del_Norte']);

it('ends the pinned business with the transaction of the request', function (string $uri) {
    Sanctum::actingAs(UserModel::factory()->make(['id' => SCOPED_ACCOUNT_ID]));

    getJson($uri)->assertOk();

    expect(DB::transactionLevel())->toBe(0)
        ->and(DB::scalar("select current_setting('app.business_id', true)"))->toBe('');
})->with([
    'panel' => '/api/v1/pruebas/panel',
    'public' => '/api/v1/pruebas/tiendas/esencias-del-norte',
    'account' => '/api/v1/pruebas/cuenta/clientes/'.SCOPED_CUSTOMER_ID,
]);

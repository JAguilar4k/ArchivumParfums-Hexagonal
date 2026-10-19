<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Tests\Support\Accounts\DemoAccounts;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

pest()->use(RefreshDatabaseAsOwner::class);

/** Whether the closure of a test route ran, which stands for reaching a use case. */
function reachedUseCase(?bool $reached = null): bool
{
    static $value = false;

    if ($reached !== null) {
        $value = $reached;
    }

    return $value;
}

/**
 * Answer the business that BusinessContext gives and the one pinned in the transaction.
 *
 * @return array{tienda: string, fijada: string}
 */
function resolvedBusiness(BusinessContext $context): array
{
    reachedUseCase(true);

    return [
        'tienda' => $context->current()->value,
        'fijada' => (string) DB::scalar("select current_setting('app.business_id', true)"),
    ];
}

/** Identifier of a demo business by its slug. */
function demoBusinessId(string $slug): string
{
    return BusinessModel::query()->where('slug', $slug)->sole()->id;
}

beforeEach(function () {
    DemoAccounts::seed();
    reachedUseCase(false);

    Route::prefix('api/v1/panel/pruebas')->middleware(['api', 'auth:sanctum', 'business:staff'])->group(function (): void {
        Route::match(['get', 'post'], 'tienda', fn (BusinessContext $context) => resolvedBusiness($context));
    });

    Route::prefix('api/v1/tiendas/{tienda}/pruebas')->middleware(['api', 'business:route'])->group(function (): void {
        Route::get('tienda', fn (BusinessContext $context) => resolvedBusiness($context));
    });
});

it('gives each person of the staff the business of the session and pins it in the transaction', function (string $email, string $slug) {
    $businessId = demoBusinessId($slug);

    getJson('/api/v1/panel/pruebas/tienda', DemoAccounts::signIn($email))
        ->assertOk()
        ->assertExactJson(['tienda' => $businessId, 'fijada' => $businessId]);
})->with([
    'owner' => ['duena@archivumparfums.test', 'archivumparfums'],
    'co-owner' => ['coduena@archivumparfums.test', 'archivumparfums'],
    'advisor' => ['asesor@archivumparfums.test', 'archivumparfums'],
    'owner of the second business' => ['dueno@esenciasdelnorte.test', 'esencias-del-norte'],
]);

it('ignores another business sent in the body or in the query of a panel request', function () {
    $own = demoBusinessId('archivumparfums');
    $other = demoBusinessId('esencias-del-norte');

    postJson("/api/v1/panel/pruebas/tienda?business_id={$other}&tienda={$other}", ['business_id' => $other, 'tienda' => $other], DemoAccounts::signIn('duena@archivumparfums.test'))
        ->assertOk()
        ->assertJsonPath('tienda', $own);
});

it('answers 401 without a token and 403 to a customer, before reaching any use case', function () {
    getJson('/api/v1/panel/pruebas/tienda')->assertUnauthorized();

    getJson('/api/v1/panel/pruebas/tienda', DemoAccounts::signIn('mariana@clientes.test'))
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/prohibido');

    expect(reachedUseCase())->toBeFalse();
});

it('takes the business of a public route from the slug of the URL and pins it', function () {
    $businessId = demoBusinessId('esencias-del-norte');

    getJson('/api/v1/tiendas/esencias-del-norte/pruebas/tienda')
        ->assertOk()
        ->assertExactJson(['tienda' => $businessId, 'fijada' => $businessId]);
});

it('answers 404 to an unknown or malformed slug before reaching any use case', function (string $slug) {
    getJson("/api/v1/tiendas/{$slug}/pruebas/tienda")
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/no-encontrado')
        ->assertJsonPath('detail', 'La tienda no existe.');

    expect(reachedUseCase())->toBeFalse();
})->with(['unknown' => 'no-existe', 'malformed' => 'Archivum_Parfums']);

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Businesses\Profile\Domain\Entities\Business;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessName;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSlug;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessTimezone;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Businesses\InMemoryBusinessRepository;

use function Pest\Laravel\getJson;

const SCOPED_BUSINESS_ID = '0192f0c4-0000-7000-8000-0000000000aa';

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
 * Business that BusinessContext gives and business pinned in the transaction.
 *
 * @return array{tienda: string, fijada: string}
 */
function requestScope(BusinessContext $context): array
{
    reachedScopedRoute(true);

    return [
        'tienda' => $context->current()->value,
        'fijada' => (string) DB::scalar("select current_setting('app.business_id', true)"),
    ];
}

beforeEach(function () {
    reachedScopedRoute(false);

    // The businesses live in memory, so these requests touch no row of the database.
    $businesses = new InMemoryBusinessRepository;
    $businesses->save(Business::create(
        new BusinessId(SCOPED_BUSINESS_ID),
        new BusinessName('Esencias del Norte'),
        new BusinessSlug('esencias-del-norte'),
        new BusinessTimezone(BusinessTimezone::DEFAULT),
        null,
    ));
    app()->instance(BusinessRepository::class, $businesses);

    // Throwaway routes that report the scope of their request.
    Route::prefix('api/v1/pruebas')->middleware('api')->group(function (): void {
        Route::get('tiendas/{tienda}', fn (BusinessContext $context): array => requestScope($context))->middleware('business:route');
    });
});

it('takes the business of a public route from the slug of the URL and pins it', function () {
    getJson('/api/v1/pruebas/tiendas/esencias-del-norte')
        ->assertOk()
        ->assertExactJson(['tienda' => SCOPED_BUSINESS_ID, 'fijada' => SCOPED_BUSINESS_ID]);
});

it('answers 404 to an unknown or malformed slug before reaching any use case', function (string $slug) {
    getJson("/api/v1/pruebas/tiendas/{$slug}")
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/no-encontrado')
        ->assertJsonPath('detail', 'La tienda no existe.');

    expect(reachedScopedRoute())->toBeFalse();
})->with(['unknown' => 'no-existe', 'malformed' => 'Esencias_del_Norte']);

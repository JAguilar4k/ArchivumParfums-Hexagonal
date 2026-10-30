<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Support\Accounts\DemoAccounts;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Security\MatrixResources;

use function Pest\Laravel\json;

pest()->use(RefreshDatabaseAsOwner::class);

/*
 * The permission matrix of section 9, row by row, as the owner, the staff, a customer and a visitor of the seeders
 * (#105). The datasets live in tests/Datasets/PermissionMatrix.php.
 */

/** Rows inserted, updated or deleted so far in the transaction of the test, besides the tokens Sanctum touches. */
function matrixRowsWritten(): int
{
    return (int) DB::scalar(
        "select coalesce(sum(n_tup_ins + n_tup_upd + n_tup_del), 0) from pg_stat_xact_user_tables
          where relname <> 'personal_access_tokens'",
    );
}

/**
 * Sign in as an account of the matrix, or as nobody for the visitor, and send the request to the URI with the
 * resources of ArchivumParfums in its parameters.
 *
 * @param  array<string, string>  $resources
 * @param  array<string, mixed>|Closure(): array<string, mixed>  $payload
 * @return array{TestResponse<JsonResponse>, int} the response and the rows written before the request
 */
function requestAsMatrixActor(string $actor, string $method, string $uri, array|Closure $payload, array $resources): array
{
    $payload = $payload instanceof Closure ? $payload() : $payload;
    $headers = $actor === 'visitante' ? [] : DemoAccounts::signIn(MatrixResources::ACCOUNTS[$actor]);
    Auth::forgetGuards();
    $path = '/'.preg_replace_callback('/\{(\w+)\}/', fn (array $name): string => $resources[$name[1]], $uri);
    $writtenBefore = matrixRowsWritten();

    return [json($method, $path, $payload, $headers), $writtenBefore];
}

foreach (array_keys(permissionMatrix()) as $row) {
    it("answers each role what section 9 allows in the row «{$row}»", function (string $method, string $uri, array $payload, array $expected, string $role) {
        [$response, $writtenBefore] = requestAsMatrixActor($role, $method, $uri, $payload, MatrixResources::create());

        $response->assertStatus($expected[$role]);

        if (in_array($expected[$role], [401, 403], true)) {
            $type = $expected[$role] === 401 ? 'no-autenticado' : 'prohibido';
            $response->assertHeader('Content-Type', 'application/problem+json')
                ->assertJsonPath('type', rtrim((string) config('app.url'), '/')."/problemas/{$type}");
            expect(matrixRowsWritten())->toBe($writtenBefore, "{$method} {$uri} wrote rows as {$role}.");
        }
    })->with("matriz: {$row}")->with('roles');

    if (permissionMatrixFromAnotherBusiness($row) !== []) {
        it("answers 404 from another business in the row «{$row}» and leaves the resource as it was", function (string $method, string $uri, array|Closure $payload, string $actor) {
            [$response, $writtenBefore] = requestAsMatrixActor($actor, $method, $uri, $payload, MatrixResources::create());

            $response->assertNotFound()
                ->assertHeader('Content-Type', 'application/problem+json')
                ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/no-encontrado');
            expect(matrixRowsWritten())->toBe($writtenBefore, "{$method} {$uri} wrote rows for {$actor}.");
        })->with("matriz desde otra tienda: {$row}");
    }
}

it('answers 422 in the field when an identifier of the body belongs to another business', function (string $actor, string $method, string $uri, Closure $payload, string $field) {
    $resources = MatrixResources::create();

    [$response, $writtenBefore] = requestAsMatrixActor($actor, $method, $uri, $payload(MatrixResources::foreignPresentation()), $resources);

    $response->assertUnprocessable()
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/datos-invalidos')
        ->assertJsonValidationErrors([$field], 'errores');
    expect(matrixRowsWritten())->toBe($writtenBefore);
})->with([
    'a presentation of another business in an order' => ['cliente', 'POST', 'api/v1/tiendas/{tienda}/pedidos',
        fn (string $presentation): array => ['lineas' => [['presentacion_id' => $presentation, 'cantidad' => 1]]], 'lineas.0.presentacion_id'],
    'a presentation of another business in a bottle' => ['dueno', 'POST', 'api/v1/panel/botellas',
        fn (string $presentation): array => ['presentacion_id' => $presentation, 'abierta_en' => '2026-11-02'], 'presentacion_id'],
]);

it('puts every route of /api/v1/panel and /api/v1/cuenta in a row of the matrix', function () {
    $covered = [];

    foreach (permissionMatrix() as $endpoints) {
        foreach ($endpoints as [$method, $uri]) {
            $covered["{$method} {$uri}"] = true;
        }
    }

    $missing = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $uri = $route->uri();

        if (! str_starts_with($uri, 'api/v1/panel') && ! str_starts_with($uri, 'api/v1/cuenta')) {
            continue;
        }

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            if (! isset($covered["{$method} {$uri}"])) {
                $missing[] = "{$method} {$uri}";
            }
        }
    }

    expect($missing)->toBe([], 'Add these endpoints to their row in tests/Datasets/PermissionMatrix.php.');
});

// Rows of section 9 whose endpoints do not exist yet: each one is completed by the issue that creates them.
it('answers each role what section 9 allows in the row «ver citas»')->todo('#137: GET /api/v1/panel/citas y GET /api/v1/cuenta/citas');
it('answers each role what section 9 allows in the row «marcar atendida o no asistió»')->todo('#138: POST /api/v1/panel/citas/{cita}/asistencia');
it('answers each role what section 9 allows in the row «reservar, reprogramar o cancelar citas»')->todo('#62, #65, #66 y #67: /api/v1/tiendas/{tienda}/citas, /api/v1/cuenta/citas y /api/v1/panel/citas');
it('answers each role what section 9 allows in the row «paquetes de decants»')->todo('#148: /api/v1/panel/paquetes y /api/v1/cuenta/paquetes-comprados');
it('answers each role what section 9 allows in the row «reportes e ingresos»')->todo('/api/v1/panel/reportes/ingresos y /api/v1/panel/reportes/mas-vendidos');
it('lets every role and the visitor use the recommender, without 401 or 403')->todo('#118: POST /api/v1/tiendas/{tienda}/recomendaciones');

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\get;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

pest()->use(RefreshDatabaseAsOwner::class);

const OPENAPI_CONTRACT = 'openapi/v1.json';

/**
 * Generate the contract from the code of the routes, without touching the versioned file.
 *
 * Generating it is the heaviest thing the suite does, so the tests share one generation.
 *
 * @return array<string, mixed>
 */
function generatedContract(): array
{
    static $contract = null;

    if ($contract !== null) {
        return $contract;
    }

    // Scramble infers the types of the whole framework, which needs more than PHP's default 128M.
    ini_set('memory_limit', '1G');

    $path = tempnam(sys_get_temp_dir(), 'openapi');

    try {
        Artisan::call('scramble:clear');
        Artisan::call('scramble:export', ['--path' => $path]);

        return $contract = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    } finally {
        unlink($path);
    }
}

it('matches the versioned contract, so the API does not change unnoticed', function () {
    $versioned = json_decode((string) file_get_contents(base_path(OPENAPI_CONTRACT)), true, flags: JSON_THROW_ON_ERROR);

    expect(generatedContract())->toEqual(
        $versioned,
        'The API changed: run `composer openapi` and commit '.OPENAPI_CONTRACT.'.',
    );
});

it('describes the routes under /api/v1 with OpenAPI 3.1', function () {
    $contract = generatedContract();

    expect($contract['openapi'])->toStartWith('3.1')
        ->and($contract['servers'])->toBe([['url' => '/api/v1']])
        ->and($contract['info']['version'])->toBe('1.0.0')
        ->and(array_keys($contract['paths']))->toContain('/sesiones', '/sesiones/actual', '/cuenta')
        ->and(array_keys($contract['paths']))->each->not->toStartWith('/docs');
});

it('documents every error of every operation as an RFC 9457 problem', function () {
    $documented = 0;

    foreach (generatedContract()['paths'] as $uri => $path) {
        foreach ($path as $method => $operation) {
            $name = strtoupper($method).' '.$uri;

            expect(isset($operation['responses']['500']))->toBeTrue("{$name} does not document the 500 problem");

            foreach ($operation['responses'] as $code => $response) {
                if ((int) $code >= 400) {
                    expect(array_keys($response['content']))->toBe(['application/problem+json'], "{$name} {$code}");
                }
            }

            $documented++;
        }
    }

    expect($documented)->toBeGreaterThan(0);
});

it('documents the errores member in the 422 problem', function () {
    $contract = generatedContract();

    expect($contract['paths']['/sesiones']['post']['responses']['422']['content']['application/problem+json']['schema'])
        ->toBe(['$ref' => '#/components/schemas/ProblemaDeValidacion'])
        ->and($contract['components']['schemas']['ProblemaDeValidacion']['required'])
        ->toContain('type', 'title', 'status', 'detail', 'instance', 'errores')
        ->and($contract['components']['schemas']['Problema']['properties'])->not->toHaveKey('errores');
});

it('requires the Sanctum token only on the routes behind auth:sanctum', function () {
    $contract = generatedContract();

    expect($contract['components']['securitySchemes']['sanctum'])->toMatchArray(['type' => 'http', 'scheme' => 'bearer'])
        ->and($contract['paths']['/cuenta']['get']['security'])->toBe([['sanctum' => []]])
        ->and($contract['paths']['/sesiones/actual']['delete']['security'])->toBe([['sanctum' => []]])
        ->and($contract['paths']['/sesiones']['post'])->not->toHaveKey('security')
        ->and($contract['paths']['/']['get'])->not->toHaveKey('security')
        ->and($contract)->not->toHaveKey('security');
});

it('publishes the interactive documentation at /docs/api', function () {
    // The API has no sessions table; documentation must also work outside Pest's array session driver.
    config(['session.driver' => 'database']);

    get('/docs/api')->assertOk();
    get('/docs/api.json')->assertOk()->assertJsonPath('openapi', fn (string $version) => str_starts_with($version, '3.1'));
});

it('hides the documentation in production until the monitoring panel decides how to protect it', function () {
    config(['session.driver' => 'database']);
    app()->detectEnvironment(fn () => 'production');

    get('/docs/api')->assertForbidden();
    get('/docs/api.json')->assertForbidden();
});

/**
 * The problem schema the contract documents for an operation and a status.
 *
 * @return array<string, mixed>
 */
function documentedProblem(string $uri, string $method, int $status): array
{
    $contract = generatedContract();
    $reference = $contract['paths'][$uri][$method]['responses'][(string) $status]['content']['application/problem+json']['schema']['$ref'];

    return $contract['components']['schemas'][basename((string) $reference)];
}

it('documents the 401 problem of signing in, which needs no Sanctum token', function () {
    $operation = generatedContract()['paths']['/sesiones']['post'];

    expect($operation['responses'])->toHaveKey('401')
        ->and($operation['responses']['401']['description'])->toBe('Credenciales inválidas')
        ->and($operation)->not->toHaveKey('security')
        ->and(documentedProblem('/sesiones', 'post', 401)['properties'])->not->toHaveKey('errores');
});

it('answers rejected credentials as the contract documents them', function () {
    $documented = documentedProblem('/sesiones', 'post', 401);

    $response = postJson('/api/v1/sesiones', [
        'correo' => 'nadie@archivumparfums.test',
        'contrasena' => 'una-clave-cualquiera',
        'dispositivo' => 'Prueba del contrato',
    ])->assertUnauthorized()->assertHeader('Content-Type', 'application/problem+json');

    expect(array_keys($response->json()))->toEqualCanonicalizing($documented['required'])
        ->and($response->json())->not->toHaveKey('errores')
        ->and($response->json('status'))->toBe(401)
        ->and($response->json('type'))->toEndWith('/problemas/credenciales-invalidas');
});

it('answers missing and invalid fields as the contract documents them', function (array $body, array $fields) {
    $documented = documentedProblem('/sesiones', 'post', 422);

    $response = postJson('/api/v1/sesiones', $body)
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json');

    expect($documented['required'])->toContain('errores')
        ->and(array_keys($response->json()))->toEqualCanonicalizing($documented['required'])
        ->and(array_keys($response->json('errores')))->toEqualCanonicalizing($fields)
        ->and($response->json('type'))->toEndWith('/problemas/datos-invalidos');
})->with([
    'every field missing' => [[], ['correo', 'contrasena', 'dispositivo']],
    'an email that is not one' => [['correo' => 'no-es-un-correo', 'contrasena' => 'x', 'dispositivo' => 'Celular'], ['correo']],
]);

it('accepts as the contract documents it a partial update with only the name', function () {
    TwoBusinesses::create();
    TwoBusinesses::actAsOwnerOf(TwoBusinesses::FIRST);

    $contract = generatedContract();
    $reference = $contract['paths']['/panel/tienda']['patch']['requestBody']['content']['application/json']['schema']['$ref'];
    $required = $contract['components']['schemas'][basename((string) $reference)]['required'] ?? [];
    $body = ['nombre' => 'Solo nombre'];

    patchJson('/api/v1/panel/tienda', $body)
        ->assertOk()
        ->assertJsonPath('data.nombre', 'Solo nombre');

    expect(array_diff($required, array_keys($body)))->toBe([]);
});

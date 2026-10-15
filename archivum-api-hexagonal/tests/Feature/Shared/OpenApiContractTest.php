<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\get;

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
    get('/docs/api')->assertOk();
    get('/docs/api.json')->assertOk()->assertJsonPath('openapi', fn (string $version) => str_starts_with($version, '3.1'));
});

it('hides the documentation in production until the monitoring panel decides how to protect it', function () {
    app()->detectEnvironment(fn () => 'production');

    get('/docs/api')->assertForbidden();
});

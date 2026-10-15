<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

const REQUEST_ID_HEADER = 'X-Request-Id';

it('gives every response a generated version 7 identifier', function () {
    $requestId = getJson('/api/v1')->assertOk()->headers->get(REQUEST_ID_HEADER);

    expect(Str::isUuid($requestId))->toBeTrue()
        ->and(substr((string) $requestId, 14, 1))->toBe('7');
});

it('gives error responses the identifier too', function () {
    $requestId = getJson('/api/v1/desconocido')->assertNotFound()->headers->get(REQUEST_ID_HEADER);

    expect(Str::isUuid($requestId))->toBeTrue();
});

it('covers the health check outside the api prefix', function () {
    $requestId = get('/up')->assertOk()->headers->get(REQUEST_ID_HEADER);

    expect(Str::isUuid($requestId))->toBeTrue();
});

it('keeps a well-formed incoming identifier', function () {
    $incoming = '0192F0C4-7B1E-7C3A-9F1D-2B6A4E8C0D11';

    getJson('/api/v1', [REQUEST_ID_HEADER => $incoming])
        ->assertHeader(REQUEST_ID_HEADER, Str::lower($incoming));
});

it('replaces an identifier that is not a uuid', function (string $incoming) {
    $requestId = getJson('/api/v1', [REQUEST_ID_HEADER => $incoming])->headers->get(REQUEST_ID_HEADER);

    expect(Str::isUuid($requestId))->toBeTrue()
        ->and($requestId)->not->toBe($incoming);
})->with([
    'free text' => 'drop table',
    'empty value' => '',
    'uuid with extra text' => '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d11x',
]);

it('shares the identifier with the log context of the request', function () {
    $requestId = getJson('/api/v1')->headers->get(REQUEST_ID_HEADER);

    expect(Context::get('request_id'))->toBe($requestId);
});

it('adds the identifier to every log entry of the request', function () {
    $path = storage_path('logs/request-id-test.log');
    @unlink($path);
    config(['logging.default' => 'request-id-test', 'logging.channels.request-id-test' => ['driver' => 'single', 'path' => $path]]);
    Route::get('/api/v1/registro-de-prueba', function () {
        Log::info('inside the request');

        return response()->noContent();
    });

    $requestId = getJson('/api/v1/registro-de-prueba')->headers->get(REQUEST_ID_HEADER);

    expect(file_get_contents($path))->toContain('inside the request')
        ->toContain('"request_id":"'.$requestId.'"');

    unlink($path);
});

it('exposes the header to the web client through cors', function () {
    getJson('/api/v1', ['Origin' => 'http://localhost:5173'])
        ->assertHeader('Access-Control-Expose-Headers', REQUEST_ID_HEADER);
});

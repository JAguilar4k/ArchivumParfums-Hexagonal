<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Src\Shared\Infrastructure\Http\Middleware\AssignRequestId;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\call;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\withHeaders;

/** Run the test with the configuration of production. */
function asProduction(): void
{
    app()->detectEnvironment(fn (): string => 'production');
    config(['app.debug' => false, 'app.url' => 'http://archivum.test']);
}

/**
 * Answer through HTTPS, as the proxy of the platform leaves a request.
 *
 * @return TestResponse<Response>
 */
function secureGet(string $uri): TestResponse
{
    return call('GET', 'https://archivum.test'.$uri, server: ['HTTP_ACCEPT' => 'application/json']);
}

it('sends the security headers on every response of the API in production', function (string $uri, int $status) {
    asProduction();

    secureGet($uri)
        ->assertStatus($status)
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'")
        ->assertHeaderMissing('X-Powered-By');
})->with([
    'a successful response' => ['/api/v1', 200],
    'a route that does not exist' => ['/api/v1/no-existe', 404],
    'a route that needs a session' => ['/api/v1/cuenta', 401],
    'the health check' => ['/up', 200],
]);

it('takes away an X-Powered-By that the application added', function () {
    asProduction();
    Route::get('/api/v1/prueba/powered', fn () => response()->noContent()->header('X-Powered-By', 'PHP/8.4'));

    secureGet('/api/v1/prueba/powered')->assertNoContent()->assertHeaderMissing('X-Powered-By');
});

it('sends HSTS only in production, where everything goes through HTTPS', function () {
    getJson('/api/v1')->assertOk()
        ->assertHeaderMissing('Strict-Transport-Security')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('gives the documentation of the API its own content policy', function () {
    $response = get('/docs/api');

    expect((string) $response->headers->get('Content-Security-Policy'))->toContain('https://unpkg.com')
        ->and((string) $response->headers->get('Content-Security-Policy'))->not->toContain("default-src 'none'");
});

it('redirects a plain HTTP request to HTTPS in production, without serving it', function () {
    asProduction();

    getJson('/api/v1?pagina=2')
        ->assertStatus(308)
        ->assertHeader('Location', 'https://archivum.test/api/v1?pagina=2');
});

it('does not redirect the documentation, which production answers with a 403 on any scheme', function () {
    asProduction();

    get('/docs/api')->assertForbidden()->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('takes the host of the redirect from APP_URL, not from the request', function () {
    asProduction();

    withHeaders(['Host' => 'atacante.example'])->getJson('/api/v1')
        ->assertStatus(308)
        ->assertHeader('Location', 'https://archivum.test/api/v1');
});

it('serves a request that arrives through HTTPS in production', function () {
    asProduction();

    secureGet('/api/v1')->assertOk();
});

it('recognises HTTPS behind the trusted proxy of the platform', function () {
    asProduction();
    config(['trustedproxy.proxies' => '127.0.0.1']);

    getJson('/api/v1', ['X-Forwarded-Proto' => 'https'])->assertOk();
});

it('does not believe an X-Forwarded-Proto from anybody else', function () {
    asProduction();
    config(['trustedproxy.proxies' => '10.0.0.1']);

    getJson('/api/v1', ['X-Forwarded-Proto' => 'https'])->assertStatus(308);
});

it('does not redirect in the other environments', function () {
    getJson('/api/v1')->assertOk();
});

it('accepts in CORS only the origin of archivum-web', function () {
    getJson('/api/v1', ['Origin' => config('archivum.web_origin')])
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', config('archivum.web_origin'));
    getJson('/api/v1', ['Origin' => 'https://otro-sitio.example'])
        ->assertOk()
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});

it('takes the origin of config/cors.php from config/archivum.php', function () {
    expect(config('cors.allowed_origins'))->toBe([])
        ->and(config('cors.allowed_origins_patterns'))->toBe(['#^'.preg_quote(config()->string('archivum.web_origin'), '#').'$#']);
});

it('answers a preflight only for the origin of archivum-web', function () {
    $preflight = fn (string $origin) => call('OPTIONS', '/api/v1/sesiones', server: [
        'HTTP_ORIGIN' => $origin,
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);

    expect($preflight((string) config('archivum.web_origin'))->headers->get('Access-Control-Allow-Origin'))->toBe(config('archivum.web_origin'))
        ->and($preflight('https://otro-sitio.example')->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
});

it('answers an unexpected error as 500 error-interno without any detail, and the log keeps it with its X-Request-Id', function () {
    asProduction();
    $path = tempnam(sys_get_temp_dir(), 'archivum-log');
    assert(is_string($path));
    config(['logging.default' => 'single', 'logging.channels.single.path' => $path]);
    Log::forgetChannel('single');
    Route::get('/api/v1/prueba/error', function (): never {
        throw new RuntimeException('select * from users where secreto_interno = 1');
    });

    $response = secureGet('/api/v1/prueba/error');

    $response->assertStatus(500)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', 'http://archivum.test/problemas/error-interno')
        ->assertJsonPath('detail', __('problems.error-interno.detail'));

    $body = (string) $response->getContent();

    expect($body)->not->toContain('secreto_interno')
        ->and($body)->not->toContain('select *')
        ->and($body)->not->toContain('ProductionHardeningTest')
        ->and($response->json())->not->toHaveKeys(['trace', 'file', 'line', 'exception', 'message']);

    $requestId = (string) $response->headers->get(AssignRequestId::HEADER);
    $log = (string) file_get_contents($path);
    Log::forgetChannel('single');
    Context::flush();
    unlink($path);

    expect($log)->toContain('secreto_interno')
        ->and($log)->toContain($requestId);
});

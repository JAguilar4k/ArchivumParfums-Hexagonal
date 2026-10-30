<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Infrastructure\RateLimitsServiceProvider;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\travel;
use function Pest\Laravel\withServerVariables;

pest()->use(RefreshDatabaseAsOwner::class);

const LIMITED_PASSWORD = 'una-clave-bien-larga';

/** Set the attempts and the window of a limiter of config/archivum.php. */
function limitTo(string $limiter, int $attempts, int $minutes = 1): void
{
    config(["archivum.rate_limits.{$limiter}" => ['attempts' => $attempts, 'minutes' => $minutes]]);
}

/**
 * Send a JSON POST from an IP address.
 *
 * @param  array<string, mixed>  $body
 * @return TestResponse<JsonResponse>
 */
function postFrom(string $ip, string $uri, array $body = []): TestResponse
{
    return withServerVariables(['REMOTE_ADDR' => $ip])->postJson($uri, $body);
}

/**
 * Attempt a sign-in from an IP address.
 *
 * @return TestResponse<JsonResponse>
 */
function signInFrom(string $ip, string $email, string $password = LIMITED_PASSWORD): TestResponse
{
    return postFrom($ip, '/api/v1/sesiones', ['correo' => $email, 'contrasena' => $password, 'dispositivo' => 'Prueba']);
}

/**
 * Expect the RFC 9457 document of a passed limit, with the seconds to wait.
 *
 * @param  TestResponse<JsonResponse>  $response
 */
function expectTooManyRequests(TestResponse $response): void
{
    $response->assertTooManyRequests()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertHeader('Retry-After')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/demasiadas-solicitudes')
        ->assertJsonPath('status', 429);

    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0);
}

it('answers 429 by IP on POST /sesiones even when the password is correct, until the window ends', function () {
    limitTo('sign_in_by_ip', 2);
    $user = UserModel::factory()->create(['password' => bcrypt(LIMITED_PASSWORD)]);

    signInFrom('10.0.0.1', $user->email)->assertCreated();
    signInFrom('10.0.0.1', 'otra@cuentas.test', 'clave-equivocada')->assertUnauthorized();

    expectTooManyRequests(signInFrom('10.0.0.1', $user->email));

    signInFrom('10.0.0.2', $user->email)->assertCreated();

    travel(61)->seconds();

    signInFrom('10.0.0.1', $user->email)->assertCreated();
});

it('answers 429 by email on POST /sesiones from any IP, until the window ends', function () {
    limitTo('sign_in_by_email', 2, 15);
    $user = UserModel::factory()->create(['password' => bcrypt(LIMITED_PASSWORD)]);

    signInFrom('10.0.0.1', $user->email, 'clave-equivocada')->assertUnauthorized();
    signInFrom('10.0.0.2', strtoupper($user->email), 'clave-equivocada')->assertUnauthorized();

    expectTooManyRequests(signInFrom('10.0.0.3', $user->email));

    signInFrom('10.0.0.3', 'otra@cuentas.test', 'clave-equivocada')->assertUnauthorized();

    travel(15)->minutes();
    travel(1)->seconds();

    signInFrom('10.0.0.3', $user->email)->assertCreated();
});

it('does not keep a person out of their account below the limit by email', function () {
    $user = UserModel::factory()->create(['password' => bcrypt(LIMITED_PASSWORD)]);

    foreach (range(1, config()->integer('archivum.rate_limits.sign_in_by_email.attempts') - 1) as $attempt) {
        signInFrom("10.0.1.{$attempt}", $user->email, 'clave-equivocada')->assertUnauthorized();
    }

    signInFrom('10.0.2.1', $user->email)->assertCreated();
});

it('answers 429 by IP on POST /cuentas, until the window ends', function () {
    limitTo('sign_up_by_ip', 1, 60);

    $account = fn (int $number): array => ['nombre' => 'Cliente', 'correo' => "cliente{$number}@cuentas.test", 'contrasena' => LIMITED_PASSWORD];

    postFrom('10.0.0.1', '/api/v1/cuentas', $account(1))->assertCreated();

    expectTooManyRequests(postFrom('10.0.0.1', '/api/v1/cuentas', $account(2)));

    postFrom('10.0.0.2', '/api/v1/cuentas', $account(3))->assertCreated();

    travel(60)->minutes();
    travel(1)->seconds();

    postFrom('10.0.0.1', '/api/v1/cuentas', $account(2))->assertCreated();
});

it('answers 429 to a visitor of the recommender by IP, until the window ends', function () {
    limitTo('recommendations_by_ip', 1);
    Route::post('/prueba/recomendaciones', fn () => response()->noContent())->middleware('throttle:'.RateLimitsServiceProvider::RECOMMENDATIONS);

    postFrom('10.0.0.1', '/prueba/recomendaciones')->assertNoContent();

    expectTooManyRequests(postFrom('10.0.0.1', '/prueba/recomendaciones'));

    postFrom('10.0.0.2', '/prueba/recomendaciones')->assertNoContent();

    travel(61)->seconds();

    postFrom('10.0.0.1', '/prueba/recomendaciones')->assertNoContent();
});

it('answers 429 to a signed-in person of the recommender by account, from any IP', function () {
    limitTo('recommendations_by_account', 1);
    Route::post('/prueba/recomendaciones', fn () => response()->noContent())->middleware('throttle:'.RateLimitsServiceProvider::RECOMMENDATIONS);
    Sanctum::actingAs(UserModel::factory()->create());

    postFrom('10.0.0.1', '/prueba/recomendaciones')->assertNoContent();

    expectTooManyRequests(postFrom('10.0.0.2', '/prueba/recomendaciones'));

    Sanctum::actingAs(UserModel::factory()->create());

    postFrom('10.0.0.2', '/prueba/recomendaciones')->assertNoContent();
});

it('answers 429 to a webhook by IP, until the window ends', function () {
    limitTo('webhooks_by_ip', 1);
    Route::post('/prueba/webhook', fn () => response()->noContent())->middleware('throttle:'.RateLimitsServiceProvider::WEBHOOKS);

    postFrom('10.0.0.1', '/prueba/webhook')->assertNoContent();

    expectTooManyRequests(postFrom('10.0.0.1', '/prueba/webhook'));

    postFrom('10.0.0.2', '/prueba/webhook')->assertNoContent();

    travel(61)->seconds();

    postFrom('10.0.0.1', '/prueba/webhook')->assertNoContent();
});

it('does not stop the normal retries of a gateway with the default webhook limit', function () {
    Route::post('/prueba/webhook', fn () => response()->noContent())->middleware('throttle:'.RateLimitsServiceProvider::WEBHOOKS);

    foreach (range(1, 100) as $retry) {
        postFrom('10.0.0.1', '/prueba/webhook')->assertNoContent();
    }
});

it('reads every limit from config/archivum.php', function (string $limiter) {
    expect(config()->integer("archivum.rate_limits.{$limiter}.attempts"))->toBeGreaterThan(0)
        ->and(config()->integer("archivum.rate_limits.{$limiter}.minutes"))->toBeGreaterThan(0);
})->with(['sign_in_by_ip', 'sign_in_by_email', 'sign_up_by_ip', 'recommendations_by_ip', 'recommendations_by_account', 'webhooks_by_ip']);

// These routes do not exist yet: each issue applies the limiter already named here to its own route.
it('applies the limiter to POST /api/v1/tiendas/{tienda}/recomendaciones')->todo('#118');
it('applies the limiter to POST /api/v1/webhooks/onvo and POST /api/v1/webhooks/paypal')->todo('#94');

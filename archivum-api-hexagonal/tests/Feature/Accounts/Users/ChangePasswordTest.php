<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;
use function Pest\Laravel\travel;

pest()->use(RefreshDatabaseAsOwner::class);

const CURRENT_PASSWORD = 'clave-actual-11';
const NEW_PASSWORD = 'clave-nueva-222';

/** Give the account a known password and open a session from a device, through the API. */
function openSession(UserModel $user, string $device = 'Computadora', string $password = CURRENT_PASSWORD): string
{
    $user->forceFill(['password' => bcrypt(CURRENT_PASSWORD)])->save();

    return (string) postJson('/api/v1/sesiones', ['correo' => $user->email, 'contrasena' => $password, 'dispositivo' => $device])
        ->assertCreated()
        ->json('data.token');
}

/**
 * Change the password with a bearer token.
 *
 * @param  array<string, mixed>  $body
 * @return TestResponse<JsonResponse>
 */
function changePassword(string $token, array $body = ['contrasena_actual' => CURRENT_PASSWORD, 'contrasena_nueva' => NEW_PASSWORD]): TestResponse
{
    Auth::forgetGuards();

    return putJson('/api/v1/cuenta/contrasena', $body, ['Authorization' => "Bearer {$token}"]);
}

it('answers 204 to the owner, the staff member and the customer', function (string $person) {
    $user = StaffedBusiness::create()->{$person};

    changePassword(openSession($user))->assertNoContent();

    Auth::forgetGuards();
    postJson('/api/v1/sesiones', ['correo' => $user->email, 'contrasena' => NEW_PASSWORD, 'dispositivo' => 'Prueba'])->assertCreated();
    Auth::forgetGuards();
    postJson('/api/v1/sesiones', ['correo' => $user->email, 'contrasena' => CURRENT_PASSWORD, 'dispositivo' => 'Prueba'])->assertUnauthorized();
})->with(['owner', 'staff', 'customer']);

it('answers 401 without a session', function () {
    putJson('/api/v1/cuenta/contrasena', ['contrasena_actual' => CURRENT_PASSWORD, 'contrasena_nueva' => NEW_PASSWORD])
        ->assertUnauthorized();
});

it('answers 422 in contrasena_actual when it is wrong, and changes nothing', function () {
    $user = StaffedBusiness::create()->customer;
    $token = openSession($user);
    $hash = $user->refresh()->password;

    changePassword($token, ['contrasena_actual' => 'otra-clave-333', 'contrasena_nueva' => NEW_PASSWORD])
        ->assertUnprocessable()
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/datos-invalidos')
        ->assertJsonValidationErrors(['contrasena_actual'], 'errores');

    expect($user->refresh()->password)->toBe($hash);
});

it('answers 422 in contrasena_nueva when it breaks the policy, and changes nothing', function () {
    $user = StaffedBusiness::create()->customer;
    $token = openSession($user);
    $hash = $user->refresh()->password;

    changePassword($token, ['contrasena_actual' => CURRENT_PASSWORD, 'contrasena_nueva' => 'corta'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['contrasena_nueva'], 'errores');

    expect($user->refresh()->password)->toBe($hash);
});

it('closes the other sessions and keeps the one of the request', function () {
    $user = StaffedBusiness::create()->customer;
    $kept = openSession($user);
    Auth::forgetGuards();
    $other = (string) postJson('/api/v1/sesiones', ['correo' => $user->email, 'contrasena' => CURRENT_PASSWORD, 'dispositivo' => 'Teléfono'])->json('data.token');

    changePassword($kept)->assertNoContent();

    Auth::forgetGuards();
    Pest\Laravel\getJson('/api/v1/cuenta', ['Authorization' => "Bearer {$other}"])->assertUnauthorized();
    Auth::forgetGuards();
    Pest\Laravel\getJson('/api/v1/cuenta', ['Authorization' => "Bearer {$kept}"])->assertOk();
});

it('keeps the spaces of a password exactly as typed, as a sign-in reads them', function () {
    $user = StaffedBusiness::create()->customer;

    changePassword(openSession($user), ['contrasena_actual' => CURRENT_PASSWORD, 'contrasena_nueva' => '  con espacios  '])->assertNoContent();

    Auth::forgetGuards();
    postJson('/api/v1/sesiones', ['correo' => $user->email, 'contrasena' => '  con espacios  ', 'dispositivo' => 'Prueba'])->assertCreated();
});

it('answers 429 by account with Retry-After, until the window ends', function () {
    config(['archivum.rate_limits.password_change_by_account' => ['attempts' => 1, 'minutes' => 1]]);
    $user = StaffedBusiness::create()->customer;
    $token = openSession($user);

    changePassword($token, ['contrasena_actual' => 'otra-clave-333', 'contrasena_nueva' => NEW_PASSWORD])->assertUnprocessable();

    changePassword($token)
        ->assertTooManyRequests()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertHeader('Retry-After');

    travel(61)->seconds();

    changePassword($token)->assertNoContent();
});

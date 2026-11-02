<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Accounts\Users\Infrastructure\Mail\PasswordResetMessage;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Accounts\Users\Infrastructure\Queue\SendPasswordResetLink;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;
use function Pest\Laravel\withServerVariables;

pest()->use(RefreshDatabaseAsOwner::class);

const RECOVERED_PASSWORD = 'clave-recuperada-1';

/**
 * Ask for a recovery, from an IP address.
 *
 * @return TestResponse<JsonResponse>
 */
function askForRecovery(string $email, string $ip = '10.0.0.1'): TestResponse
{
    return withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/v1/recuperaciones-de-contrasena', ['correo' => $email]);
}

/**
 * Confirm a recovery, from an IP address.
 *
 * @return TestResponse<JsonResponse>
 */
function confirmRecovery(string $email, string $token, string $password = RECOVERED_PASSWORD, string $ip = '10.0.0.1'): TestResponse
{
    return withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/v1/recuperaciones-de-contrasena/confirmacion', [
        'correo' => $email,
        'token' => $token,
        'contrasena_nueva' => $password,
    ]);
}

/** The token inside the link of the only mail sent. */
function tokenOfTheMail(): string
{
    $sent = Mail::sent(PasswordResetMessage::class);
    expect($sent)->toHaveCount(1);
    $message = $sent->first();
    assert($message instanceof PasswordResetMessage);
    parse_str((string) parse_url($message->link, PHP_URL_QUERY), $query);

    assert(is_string($query['token']));

    return $query['token'];
}

beforeEach(fn () => Mail::fake());

it('answers 202 with the same body whether or not the account exists', function () {
    $user = UserModel::factory()->create();

    $existing = askForRecovery($user->email)->assertAccepted();
    $missing = askForRecovery('nadie@cuentas.test')->assertAccepted();

    expect($existing->getContent())->toBe($missing->getContent())
        ->and($existing->json('data.mensaje'))->toBe(__('accounts.password_reset_requested'));
});

it('queues the same job for an email with or without an account, so the time tells nothing', function () {
    Queue::fake();
    $user = UserModel::factory()->create();

    askForRecovery($user->email)->assertAccepted();
    askForRecovery('nadie@cuentas.test')->assertAccepted();

    Queue::assertPushed(SendPasswordResetLink::class, 2);
    expect(DB::table('password_reset_tokens')->count())->toBe(0);
    Mail::assertNothingSent();
});

it('sends one mail only when the account exists, to its email', function () {
    $user = UserModel::factory()->create();

    askForRecovery('nadie@cuentas.test');
    Mail::assertNothingSent();

    askForRecovery($user->email);

    Mail::assertSent(PasswordResetMessage::class, 1);
    Mail::assertSent(PasswordResetMessage::class, fn (PasswordResetMessage $message): bool => $message->hasTo($user->email));
});

it('sends one mail per minute to an account, and answers the same', function () {
    $user = UserModel::factory()->create();

    askForRecovery($user->email)->assertAccepted();
    askForRecovery($user->email)->assertAccepted();

    Mail::assertSent(PasswordResetMessage::class, 1);

    travel(61)->seconds();
    askForRecovery($user->email)->assertAccepted();

    Mail::assertSent(PasswordResetMessage::class, 2);
});

it('builds the link with the address of archivum-web, never with the Host of the request', function () {
    config(['archivum.web_origin' => 'https://web.archivum.test']);
    $user = UserModel::factory()->create();

    Pest\Laravel\withHeaders(['Host' => 'atacante.example'])->postJson('/api/v1/recuperaciones-de-contrasena', ['correo' => $user->email])->assertAccepted();

    $message = Mail::sent(PasswordResetMessage::class)->first();
    assert($message instanceof PasswordResetMessage);

    expect($message->link)->toStartWith('https://web.archivum.test/recuperar-contrasena?token=')
        ->and($message->link)->not->toContain($user->email);
});

it('writes the mail from lang/es, with the link', function () {
    $message = new PasswordResetMessage('https://web.archivum.test/recuperar-contrasena?token=abc');

    $message->assertHasSubject(__('accounts.password_reset_subject'));
    $message->assertSeeInHtml('https://web.archivum.test/recuperar-contrasena?token=abc');
    $message->assertSeeInHtml(__('accounts.password_reset_action'));
});

it('stores only the hash of the token and never answers it', function () {
    $user = UserModel::factory()->create();

    $response = askForRecovery($user->email);
    $token = tokenOfTheMail();

    expect(DB::table('password_reset_tokens')->where('email', $user->email)->value('token'))->not->toBe($token)
        ->and((string) $response->getContent())->not->toContain($token);
});

it('sets the password, closes every session and lets the person in only with the new one', function () {
    $user = UserModel::factory()->create(['password' => bcrypt('clave-anterior-1')]);
    $old = $user->createToken('Computadora')->plainTextToken;
    askForRecovery($user->email);

    confirmRecovery($user->email, tokenOfTheMail())->assertNoContent();

    expect(PersonalAccessToken::query()->where('tokenable_id', $user->id)->count())->toBe(0);
    Pest\Laravel\getJson('/api/v1/cuenta', ['Authorization' => "Bearer {$old}"])->assertUnauthorized();
    Auth::forgetGuards();
    postJson('/api/v1/sesiones', ['correo' => $user->email, 'contrasena' => RECOVERED_PASSWORD, 'dispositivo' => 'Prueba'])->assertCreated();
    Auth::forgetGuards();
    postJson('/api/v1/sesiones', ['correo' => $user->email, 'contrasena' => 'clave-anterior-1', 'dispositivo' => 'Prueba'])->assertUnauthorized();
});

it('answers 422 in token, and changes nothing, for a token already used', function () {
    $user = UserModel::factory()->create();
    askForRecovery($user->email);
    $token = tokenOfTheMail();
    confirmRecovery($user->email, $token)->assertNoContent();
    $hash = $user->refresh()->password;

    confirmRecovery($user->email, $token, 'otra-clave-nueva-2')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token'], 'errores');

    expect($user->refresh()->password)->toBe($hash);
});

it('answers 422 in token for a token that expired', function () {
    $user = UserModel::factory()->create();
    askForRecovery($user->email);
    $token = tokenOfTheMail();

    travel(config()->integer('auth.passwords.users.expire') + 1)->minutes();

    confirmRecovery($user->email, $token)
        ->assertUnprocessable()
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/datos-invalidos')
        ->assertJsonValidationErrors(['token'], 'errores');
});

it('accepts a token just before it expires', function () {
    $user = UserModel::factory()->create();
    askForRecovery($user->email);
    $token = tokenOfTheMail();

    travel(config()->integer('auth.passwords.users.expire') - 1)->minutes();

    confirmRecovery($user->email, $token)->assertNoContent();
});

it('answers 422 in token for a token of another email, and for an email without an account', function () {
    $user = UserModel::factory()->create();
    $other = UserModel::factory()->create();
    $hash = $other->password;
    askForRecovery($user->email);
    $token = tokenOfTheMail();

    confirmRecovery($other->email, $token)->assertUnprocessable()->assertJsonValidationErrors(['token'], 'errores');
    confirmRecovery('nadie@cuentas.test', $token)->assertUnprocessable()->assertJsonValidationErrors(['token'], 'errores');

    expect($other->refresh()->password)->toBe($hash);
});

it('answers 422 in contrasena_nueva when it breaks the policy, and keeps the token', function () {
    $user = UserModel::factory()->create();
    askForRecovery($user->email);
    $token = tokenOfTheMail();

    confirmRecovery($user->email, $token, 'corta')->assertUnprocessable()->assertJsonValidationErrors(['contrasena_nueva'], 'errores');

    confirmRecovery($user->email, $token)->assertNoContent();
});

it('lets a person added to the staff set their password and sign in with their role', function () {
    $business = actingAsOwner();
    postJson('/api/v1/panel/personal', [
        'nombre' => 'Andrea Solano',
        'correo' => 'andrea@personal.test',
        'contrasena' => 'clave-inicial-del-dueno',
        'rol' => 'colaborador',
    ])->assertCreated();
    Auth::forgetGuards();

    askForRecovery('andrea@personal.test')->assertAccepted();
    confirmRecovery('andrea@personal.test', tokenOfTheMail())->assertNoContent();

    postJson('/api/v1/sesiones', ['correo' => 'andrea@personal.test', 'contrasena' => RECOVERED_PASSWORD, 'dispositivo' => 'Prueba'])->assertCreated();
    expect(PersonalAccessToken::query()->latest('id')->firstOrFail()->abilities)->toBe(Ability::namesGrantedTo(AccountRole::Staff))
        ->and($business->business->id)->not->toBeEmpty();
});

it('answers 429 with Retry-After on the request, by IP and by email, until the window ends', function () {
    config(['archivum.password_rate_limits.request_by_ip' => ['attempts' => 1, 'minutes' => 1]]);
    askForRecovery('uno@cuentas.test')->assertAccepted();
    askForRecovery('dos@cuentas.test')->assertTooManyRequests()->assertHeader('Retry-After');
    askForRecovery('dos@cuentas.test', '10.0.0.2')->assertAccepted();

    config([
        'archivum.password_rate_limits.request_by_ip' => ['attempts' => 100, 'minutes' => 1],
        'archivum.password_rate_limits.request_by_email' => ['attempts' => 1, 'minutes' => 1],
    ]);
    travel(61)->seconds();
    askForRecovery('tres@cuentas.test', '10.0.0.3')->assertAccepted();
    askForRecovery('tres@cuentas.test', '10.0.0.4')->assertTooManyRequests()->assertHeader('Content-Type', 'application/problem+json');

    travel(61)->seconds();
    askForRecovery('tres@cuentas.test', '10.0.0.4')->assertAccepted();
});

it('answers 429 with Retry-After on the confirmation, by IP and by email, until the window ends', function () {
    config(['archivum.password_rate_limits.confirm_by_ip' => ['attempts' => 1, 'minutes' => 1]]);
    confirmRecovery('uno@cuentas.test', 'x')->assertUnprocessable();
    confirmRecovery('dos@cuentas.test', 'x')->assertTooManyRequests()->assertHeader('Retry-After');

    config([
        'archivum.password_rate_limits.confirm_by_ip' => ['attempts' => 100, 'minutes' => 1],
        'archivum.password_rate_limits.confirm_by_email' => ['attempts' => 1, 'minutes' => 1],
    ]);
    travel(61)->seconds();
    confirmRecovery('tres@cuentas.test', 'x', ip: '10.0.0.3')->assertUnprocessable();
    confirmRecovery('tres@cuentas.test', 'x', ip: '10.0.0.4')->assertTooManyRequests();

    travel(61)->seconds();
    confirmRecovery('tres@cuentas.test', 'x', ip: '10.0.0.4')->assertUnprocessable();
});

<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Tests\Support\Accounts\DemoAccounts;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\json;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;
use function Pest\Laravel\travelTo;

pest()->use(RefreshDatabaseAsOwner::class);

const EXPIRING_OWNER = 'duena@archivumparfums.test';

const EXPIRING_CUSTOMER = 'mariana@clientes.test';

beforeEach(function () {
    DemoAccounts::seed();
    config(['sanctum.expiration' => 60]);
    travelTo(Carbon::parse('2026-11-02 09:00:00', 'America/Costa_Rica'));
});

/**
 * Sign in through POST /api/v1/sesiones and answer the data of the response.
 *
 * @return array{token: string, tipo: string, vence_en: string}
 */
function signInExpiring(string $email, string $device = 'Computadora'): array
{
    /** @var array{token: string, tipo: string, vence_en: string} */
    return postJson('/api/v1/sesiones', ['correo' => $email, 'contrasena' => DemoAccounts::PASSWORD, 'dispositivo' => $device])
        ->assertCreated()
        ->json('data');
}

/**
 * Send a request with a bearer token, forgetting the account of any earlier request.
 *
 * @return TestResponse<JsonResponse>
 */
function withBearer(string $token, string $method, string $path): TestResponse
{
    Auth::forgetGuards();

    return json($method, $path, [], ['Authorization' => "Bearer {$token}"]);
}

/**
 * Assert the 401 problem of a request without a valid token.
 *
 * @param  TestResponse<JsonResponse>  $response
 */
function expectUnauthenticated(TestResponse $response): void
{
    $response->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/no-autenticado')
        ->assertJsonPath('status', 401);
}

it('tells in vence_en, in ISO 8601 with its offset, when the token of a sign-in expires', function () {
    $session = signInExpiring(EXPIRING_OWNER);

    $stored = PersonalAccessToken::findToken($session['token']);

    expect($session['vence_en'])->toBe('2026-11-02T10:00:00-06:00')
        ->and($stored?->expires_at?->equalTo(Carbon::parse($session['vence_en'])))->toBeTrue();
});

it('takes the duration from sanctum.expiration', function () {
    config(['sanctum.expiration' => 15]);

    expect(signInExpiring(EXPIRING_OWNER)['vence_en'])->toBe('2026-11-02T09:15:00-06:00');
});

it('serves a token while it is valid and answers 401 on any protected route once it expires', function (string $method, string $path) {
    $token = signInExpiring(EXPIRING_OWNER)['token'];

    travel(59)->minutes();
    expect(withBearer($token, $method, $path)->status())->not->toBe(401);

    travel(2)->minutes();
    expectUnauthenticated(withBearer($token, $method, $path));
})->with([
    'the current account' => ['GET', '/api/v1/cuenta'],
    'the panel' => ['GET', '/api/v1/panel/tienda'],
    'signing out' => ['DELETE', '/api/v1/sesiones/actual'],
]);

it('renews a valid token: 201 with a new token on the same device and a new expiration', function () {
    $old = signInExpiring(EXPIRING_OWNER, 'Celular de la tienda');
    travel(40)->minutes();

    $response = withBearer($old['token'], 'POST', '/api/v1/sesiones/actual/renovacion')
        ->assertCreated()
        ->assertJsonPath('data.tipo', 'Bearer')
        ->assertJsonPath('data.vence_en', '2026-11-02T10:40:00-06:00');

    $new = (string) $response->json('data.token');
    $owner = UserModel::query()->where('email', EXPIRING_OWNER)->sole();

    expect($new)->not->toBe($old['token'])
        ->and(array_keys($response->json('data')))->toBe(['token', 'tipo', 'vence_en'])
        ->and($owner->tokens()->sole()->name)->toBe('Celular de la tienda')
        ->and($owner->tokens()->sole()->abilities)->toEqualCanonicalizing(Ability::namesGrantedTo(AccountRole::Owner));

    withBearer($new, 'GET', '/api/v1/cuenta')->assertOk();
});

it('revokes the previous token on renewal: using it again or renewing with it answers 401', function () {
    $old = signInExpiring(EXPIRING_CUSTOMER)['token'];
    $new = (string) withBearer($old, 'POST', '/api/v1/sesiones/actual/renovacion')->assertCreated()->json('data.token');

    expectUnauthenticated(withBearer($old, 'GET', '/api/v1/cuenta'));
    expectUnauthenticated(withBearer($old, 'POST', '/api/v1/sesiones/actual/renovacion'));
    withBearer($new, 'GET', '/api/v1/cuenta')->assertOk();
});

it('keeps the new token alive past the expiration of the old one, until its own expiration', function () {
    $old = signInExpiring(EXPIRING_CUSTOMER)['token'];
    travel(50)->minutes();
    $new = (string) withBearer($old, 'POST', '/api/v1/sesiones/actual/renovacion')->assertCreated()->json('data.token');

    travel(30)->minutes();
    withBearer($new, 'GET', '/api/v1/cuenta')->assertOk();

    travel(31)->minutes();
    expectUnauthenticated(withBearer($new, 'GET', '/api/v1/cuenta'));
});

it('refuses to renew an expired token: the account has to sign in again', function () {
    $token = signInExpiring(EXPIRING_CUSTOMER)['token'];
    travel(61)->minutes();

    expectUnauthenticated(withBearer($token, 'POST', '/api/v1/sesiones/actual/renovacion'));
    expect(UserModel::query()->where('email', EXPIRING_CUSTOMER)->sole()->tokens()->count())->toBe(1);
});

it('refuses to renew a token closed with DELETE /sesiones/actual', function () {
    $token = signInExpiring(EXPIRING_CUSTOMER)['token'];
    withBearer($token, 'DELETE', '/api/v1/sesiones/actual')->assertNoContent();

    expectUnauthenticated(withBearer($token, 'POST', '/api/v1/sesiones/actual/renovacion'));
});

it('renews only the token of the request and leaves the other sessions of the account open', function () {
    $phone = signInExpiring(EXPIRING_CUSTOMER, 'Celular')['token'];
    $computer = signInExpiring(EXPIRING_CUSTOMER, 'Computadora')['token'];

    withBearer($phone, 'POST', '/api/v1/sesiones/actual/renovacion')->assertCreated();

    withBearer($computer, 'GET', '/api/v1/cuenta')->assertOk();
    expect(UserModel::query()->where('email', EXPIRING_CUSTOMER)->sole()->tokens()->pluck('name')->all())
        ->toEqualCanonicalizing(['Celular', 'Computadora']);
});

it('recalculates the abilities on renewal with the role the account has now', function () {
    $token = signInExpiring(EXPIRING_CUSTOMER)['token'];
    $customer = UserModel::query()->where('email', EXPIRING_CUSTOMER)->sole();
    MemberModel::factory()->staff()->create(['user_id' => $customer->id]);

    withBearer($token, 'POST', '/api/v1/sesiones/actual/renovacion')->assertCreated();

    expect($customer->tokens()->sole()->abilities)->toEqualCanonicalizing(Ability::namesGrantedTo(AccountRole::Staff));
});

it('asks for a token to renew', function () {
    expectUnauthenticated(postJson('/api/v1/sesiones/actual/renovacion'));
});

it('gives a single new token to two simultaneous renewals of the same token, and 401 to the other', function () {
    $admin = DB::connection('pgsql_admin');
    $userId = (string) Str::uuid7();
    $secret = Str::random(40);
    $admin->table('users')->insert([
        'id' => $userId,
        'name' => 'Cliente ficticia',
        'email' => "renovacion-{$userId}@clientes.test",
        'password' => 'unused-fictitious-hash',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $tokenId = $admin->table('personal_access_tokens')->insertGetId([
        'tokenable_type' => new UserModel()->getMorphClass(),
        'tokenable_id' => $userId,
        'name' => 'Celular',
        'token' => hash('sha256', $secret),
        'abilities' => json_encode(Ability::namesGrantedTo(AccountRole::Customer)),
        'expires_at' => now()->addHour(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $bearer = "{$tokenId}|{$secret}";

    $renew = function () use ($bearer): int {
        $request = Request::create('/api/v1/sesiones/actual/renovacion', 'POST', server: [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer {$bearer}",
        ]);

        return app(Kernel::class)->handle($request)->getStatusCode();
    };

    $statuses = inParallel(['personal_access_tokens', 'users'], $renew, $renew);

    expect($statuses)->toEqualCanonicalizing([201, 401])
        ->and($admin->table('personal_access_tokens')->where('tokenable_id', $userId)->count())->toBe(1)
        ->and($admin->table('personal_access_tokens')->where('id', $tokenId)->exists())->toBeFalse();
});

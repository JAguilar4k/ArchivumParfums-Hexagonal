<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Accounts\DemoAccounts;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\postJson;

pest()->use(RefreshDatabaseAsOwner::class);

/*
 * The personal data of the customers (#110): the phone encrypted, the notice accepted and the logs without secrets.
 */

/**
 * A valid sign-up with the privacy notice accepted.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function signUpWithNotice(array $overrides = []): array
{
    return [
        'nombre' => 'Mariana Rojas',
        'correo' => 'mariana@clientes.test',
        'contrasena' => 'una-clave-bien-larga',
        'acepta_privacidad' => true,
        ...$overrides,
    ];
}

/**
 * Write an entry through a channel to a log file of its own, and answer what the file holds.
 *
 * @param  array<string, mixed>  $context
 */
function loggedText(string $message, array $context = []): string
{
    $path = tempnam(sys_get_temp_dir(), 'archivum-log');
    assert(is_string($path));
    config(['logging.channels.single.path' => $path]);
    Log::forgetChannel('single');
    Log::channel('single')->error($message, $context);
    $text = (string) file_get_contents($path);
    Log::forgetChannel('single');
    unlink($path);

    return $text;
}

it('stores the phone encrypted: a direct query does not show the number', function () {
    $business = StaffedBusiness::create();

    $stored = app(BusinessTransaction::class)->run($business->business->id, function () use ($business): array {
        CustomerModel::query()->where('user_id', $business->customer->id)->sole()->update(['phone' => '+50688880099']);

        return [
            'raw' => (string) DB::table('customers')->where('user_id', $business->customer->id)->value('phone'),
            'model' => CustomerModel::query()->where('user_id', $business->customer->id)->sole()->phone,
        ];
    });

    expect($stored['raw'])->not->toContain('88880099')
        ->and(Crypt::decryptString($stored['raw']))->toBe('+50688880099')
        ->and($stored['model'])->toBe('+50688880099');
});

it('reads a phone encrypted with a previous key after the key rotates', function () {
    $business = StaffedBusiness::create();
    $oldKey = (string) config('app.key');

    $encrypted = Crypt::encryptString('+50688880099');
    app(BusinessTransaction::class)->run($business->business->id, fn () => DB::table('customers')->where('user_id', $business->customer->id)->update(['phone' => $encrypted]));

    config(['app.key' => 'base64:'.base64_encode(random_bytes(32)), 'app.previous_keys' => [$oldKey]]);
    app()->forgetInstance('encrypter');
    Crypt::clearResolvedInstance('encrypter');

    $phone = app(BusinessTransaction::class)->run($business->business->id, fn () => CustomerModel::query()->where('user_id', $business->customer->id)->sole()->phone);

    expect($phone)->toBe('+50688880099');
});

it('encrypts the phones already stored when it migrates, and leaves them in clear when it rolls back', function () {
    $business = StaffedBusiness::create();
    $migration = require database_path('migrations/2026_10_30_000003_encrypt_the_phones_of_customers.php');
    $read = fn (): string => (string) app(BusinessTransaction::class)->run($business->business->id, fn () => DB::table('customers')->where('user_id', $business->customer->id)->value('phone'));
    app(BusinessTransaction::class)->run($business->business->id, fn () => DB::table('customers')->where('user_id', $business->customer->id)->update(['phone' => '+50688880099']));

    $migration->up();
    expect($read())->not->toContain('88880099')
        ->and(Crypt::decryptString($read()))->toBe('+50688880099');

    $migration->down();
    expect($read())->toBe('+50688880099');
});

it('answers 422 in acepta_privacidad without the acceptance and opens no account', function (array $body) {
    postJson('/api/v1/cuentas', $body)
        ->assertUnprocessable()
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/datos-invalidos')
        ->assertJsonValidationErrors(['acepta_privacidad'], 'errores');

    expect(UserModel::query()->where('email', 'mariana@clientes.test')->exists())->toBeFalse();
})->with([
    'without the field' => [array_diff_key(signUpWithNotice(), ['acepta_privacidad' => true])],
    'rejected' => [signUpWithNotice(['acepta_privacidad' => false])],
    'with a text' => [signUpWithNotice(['acepta_privacidad' => 'quizas'])],
]);

it('keeps the date and the version of the notice the account accepted', function () {
    config(['archivum.privacy_notice_version' => '2026-11']);

    postJson('/api/v1/cuentas', signUpWithNotice())->assertCreated();

    $user = DB::table('users')->where('email', 'mariana@clientes.test')->first(['privacy_accepted_at', 'privacy_notice_version']);
    assert(is_object($user));

    expect($user->privacy_notice_version)->toBe('2026-11')
        ->and($user->privacy_accepted_at)->not->toBeNull();
});

it('takes the version of the notice from the server, not from the body', function () {
    config(['archivum.privacy_notice_version' => '2026-11']);

    postJson('/api/v1/cuentas', signUpWithNotice(['privacy_notice_version' => 'falsa', 'version_aviso' => 'falsa']))->assertCreated();

    expect(DB::table('users')->where('email', 'mariana@clientes.test')->value('privacy_notice_version'))->toBe('2026-11');
});

it('keeps passwords, tokens, emails and phones out of the message of a log entry', function () {
    $text = loggedText(
        'Falló con mariana@clientes.test, +50688880099, 12|'.str_repeat('a', 48).' y $2y$12$'.str_repeat('b', 53),
    );

    expect($text)->not->toContain('mariana@clientes.test')
        ->and($text)->not->toContain('88880099')
        ->and($text)->not->toContain(str_repeat('a', 48))
        ->and($text)->not->toContain(str_repeat('b', 53))
        ->and($text)->toContain('[correo]')
        ->and($text)->toContain('[token]');
});

it('keeps them out of the context of a log entry, at any depth', function () {
    $text = loggedText('Evento', [
        'correo' => 'mariana@clientes.test',
        'contrasena' => 'una-clave-bien-larga',
        'datos' => ['telefono' => '+50688880099', 'nota' => 'escribir a carlos@clientes.test'],
        'request_id' => '01920000-0000-7000-8000-000000000000',
    ]);

    expect($text)->not->toContain('mariana@clientes.test')
        ->and($text)->not->toContain('una-clave-bien-larga')
        ->and($text)->not->toContain('88880099')
        ->and($text)->not->toContain('carlos@clientes.test')
        ->and($text)->toContain('01920000-0000-7000-8000-000000000000');
});

it('keeps the bindings of a failed query out of the log', function () {
    $exception = null;

    try {
        DB::transaction(fn () => DB::table('users')->insert([
            ['id' => 'no-es-un-uuid', 'name' => 'Mariana', 'email' => 'mariana@clientes.test', 'password' => '$2y$12$'.str_repeat('b', 53)],
        ]));
    } catch (Throwable $thrown) {
        $exception = $thrown;
    }

    expect($exception)->not->toBeNull();
    assert($exception instanceof Throwable);

    $text = loggedText($exception->getMessage(), ['exception' => $exception]);

    expect($text)->not->toContain('mariana@clientes.test')
        ->and($text)->not->toContain(str_repeat('b', 53));
});

it('fails when a seeder creates an email outside the reserved .test domain', function () {
    DemoAccounts::seed();

    $emails = DB::table('users')->pluck('email')->all();

    expect($emails)->not->toBeEmpty();

    foreach ($emails as $email) {
        expect($email)->toEndWith('.test');
    }
});

it('stores the phones of the seeders encrypted', function () {
    DemoAccounts::seed();

    $businesses = DB::table('businesses')->pluck('id');

    foreach ($businesses as $business) {
        $phones = app(BusinessTransaction::class)->run($business, fn () => DB::table('customers')->pluck('phone')->all());

        foreach ($phones as $phone) {
            expect(Crypt::decryptString($phone))->toStartWith('+5068888');
        }
    }
});

// Pulse is not installed yet: the issue that installs it adds this proof.
it('keeps these data out of Pulse')->todo('#180');

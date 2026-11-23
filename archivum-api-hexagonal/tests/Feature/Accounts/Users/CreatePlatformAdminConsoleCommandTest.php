<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseCount;

pest()->use(RefreshDatabaseAsOwner::class);

const CREATE_PLATFORM_ADMIN = 'archivum:create-platform-admin';

/**
 * Run the command through the test console, to assert its questions, its output and its exit code.
 *
 * @param  array<string, mixed>  $arguments
 */
function createPlatformAdmin(array $arguments = []): PendingCommand
{
    $command = artisan(CREATE_PLATFORM_ADMIN, $arguments);
    assert($command instanceof PendingCommand);

    return $command;
}

it('is registered with the agreed arguments, option and spanish description', function () {
    $command = Artisan::all()[CREATE_PLATFORM_ADMIN];
    $definition = $command->getDefinition();

    expect(array_keys($definition->getArguments()))->toBe(['name', 'email'])
        ->and($definition->getOption('password')->acceptValue())->toBeTrue()
        ->and($command->getDescription())->toBe('Abre la cuenta de una persona que administra la plataforma: crea y lista tiendas, sin pertenecer a ninguna.');
});

it('opens an account marked as administrator of the platform, on no staff and with no customer record', function () {
    $exitCode = Artisan::call(CREATE_PLATFORM_ADMIN, ['name' => 'Equipo de desarrollo', 'email' => 'Dev@ArchivumParfums.test', '--password' => 'clave-segura']);

    $user = UserModel::query()->sole();

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Cuenta de administración de la plataforma creada: dev@archivumparfums.test.')
        ->and(Artisan::output())->not->toContain('clave-segura')
        ->and($user->email)->toBe('dev@archivumparfums.test')
        ->and(Hash::check('clave-segura', $user->password))->toBeTrue()
        ->and($user->getAttribute('is_platform_admin'))->toBeTrue();
    assertDatabaseCount('members', 0);
    assertDatabaseCount('customers', 0);
});

it('asks for every missing datum and hides the password', function () {
    createPlatformAdmin()
        ->expectsQuestion('Nombre de la persona', 'Equipo de desarrollo')
        ->expectsQuestion('Correo de la cuenta', 'dev@archivumparfums.test')
        ->expectsQuestion('Contraseña de la cuenta', 'clave-segura')
        ->doesntExpectOutputToContain('clave-segura')
        ->assertExitCode(0);

    expect(UserModel::query()->sole()->getAttribute('is_platform_admin'))->toBeTrue();
});

it('refuses an email that already has an account and marks nothing', function () {
    $existing = UserModel::factory()->create(['email' => 'duena@archivumparfums.test']);

    createPlatformAdmin(['name' => 'Equipo de desarrollo', 'email' => 'DUENA@archivumparfums.test', '--password' => 'clave-segura'])
        ->expectsOutputToContain('Ya existe una cuenta con ese correo.')
        ->assertExitCode(1);

    assertDatabaseCount('users', 1);
    expect($existing->refresh()->getAttribute('is_platform_admin'))->toBeFalse();
});

it('refuses invalid data in spanish and opens nothing', function (array $arguments, string $message) {
    createPlatformAdmin(['name' => 'Equipo de desarrollo', 'email' => 'dev@archivumparfums.test', '--password' => 'clave-segura', ...$arguments])
        ->expectsOutputToContain($message)
        ->assertExitCode(1);

    assertDatabaseCount('users', 0);
})->with([
    'malformed email' => [['email' => 'dev.archivumparfums.test'], 'El correo debe ser una dirección válida de 255 caracteres como máximo.'],
    'short password' => [['--password' => '1234567'], 'La contraseña debe tener al menos 8 caracteres.'],
]);

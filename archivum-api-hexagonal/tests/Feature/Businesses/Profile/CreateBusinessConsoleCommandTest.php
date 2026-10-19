<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseCount;

pest()->use(RefreshDatabaseAsOwner::class);

const CREATE_BUSINESS = 'archivum:create-business';

/**
 * Build the arguments of a valid call, overriding what a scenario changes.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function createBusinessArguments(array $overrides = []): array
{
    return [
        'name' => 'ArchivumParfums',
        'slug' => 'archivumparfums',
        'owner-name' => 'Valeria Solano Ruiz',
        'owner-email' => 'duena@archivumparfums.test',
        ...$overrides,
    ];
}

/**
 * Run the command through the test console, to assert its questions, its output and its exit code.
 *
 * @param  array<string, mixed>  $arguments
 */
function createBusiness(array $arguments = []): PendingCommand
{
    $command = artisan(CREATE_BUSINESS, $arguments);
    assert($command instanceof PendingCommand);

    return $command;
}

it('is registered with the agreed arguments, option and spanish description', function () {
    $command = Artisan::all()[CREATE_BUSINESS];
    $definition = $command->getDefinition();

    expect(array_keys($definition->getArguments()))->toBe(['name', 'slug', 'owner-name', 'owner-email'])
        ->and(array_keys($definition->getOptions()))->toContain('password')
        ->and($definition->getOption('password')->acceptValue())->toBeTrue()
        ->and($command->getDescription())->toBe('Da de alta una tienda con los ajustes por defecto y la cuenta de su dueño.');
});

it('keeps its spanish texts in lang/es instead of the signature', function () {
    $definition = Artisan::all()[CREATE_BUSINESS]->getDefinition();
    $inputs = [...$definition->getArguments(), $definition->getOption('password')];

    foreach ($inputs as $input) {
        expect($input->getDescription())->toBe('');
    }
});

it('registers the business with the default settings and its owner, without echoing the password', function () {
    $exitCode = Artisan::call(CREATE_BUSINESS, createBusinessArguments(['--password' => 'clave-segura']));

    $business = BusinessModel::query()->sole();
    $output = (string) preg_replace('/\s+/', ' ', Artisan::output());
    $owner = UserModel::query()->sole();
    $member = MemberModel::query()->sole();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain("Tienda registrada: ArchivumParfums (archivumparfums, {$business->id}). Dueño: duena@archivumparfums.test.")
        ->and($output)->not->toContain('clave-segura')
        ->and($business->timezone)->toBe('America/Costa_Rica')
        ->and($business->settings)->toBe([])
        ->and($owner->name)->toBe('Valeria Solano Ruiz')
        ->and(Hash::check('clave-segura', $owner->password))->toBeTrue()
        ->and($member->business_id)->toBe($business->id)
        ->and($member->user_id)->toBe($owner->id)
        ->and($member->role)->toBe('owner');
});

it('asks for every missing datum and hides the password', function () {
    createBusiness()
        ->expectsQuestion('Nombre de la tienda', 'ArchivumParfums')
        ->expectsQuestion('Slug de la tienda (minúsculas, dígitos y guiones)', 'archivumparfums')
        ->expectsQuestion('Nombre del dueño', 'Valeria Solano Ruiz')
        ->expectsQuestion('Correo del dueño', 'Duena@ArchivumParfums.test')
        ->expectsQuestion('Contraseña del dueño', 'clave-segura')
        ->expectsOutputToContain('Dueño: duena@archivumparfums.test.')
        ->doesntExpectOutputToContain('clave-segura')
        ->assertExitCode(0);

    expect(BusinessModel::query()->sole()->slug)->toBe('archivumparfums')
        ->and(Hash::check('clave-segura', UserModel::query()->sole()->password))->toBeTrue();
});

it('pins the new business in the transaction before writing its staff', function () {
    $statements = [];
    DB::listen(function (QueryExecuted $query) use (&$statements): void {
        $statements[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
    });

    Artisan::call(CREATE_BUSINESS, createBusinessArguments(['--password' => 'clave-segura']));

    $businessId = BusinessModel::query()->sole()->id;
    $scope = array_find_key($statements, fn (array $statement): bool => $statement['bindings'] === [BusinessTransaction::SETTING, $businessId]);
    $member = array_find_key($statements, fn (array $statement): bool => str_starts_with($statement['sql'], 'insert into "members"'));

    expect($scope)->toBeInt()
        ->and($member)->toBeInt()
        ->and($scope < $member)->toBeTrue();
});

it('refuses a repeated slug in spanish and creates nothing', function () {
    createBusiness(createBusinessArguments(['--password' => 'clave-segura']))->assertExitCode(0);

    createBusiness(createBusinessArguments([
        'name' => 'Otra tienda',
        'owner-email' => 'otro@archivumparfums.test',
        '--password' => 'clave-segura',
    ]))
        ->expectsOutputToContain('Ya existe una tienda con ese slug.')
        ->assertExitCode(1);

    expect(BusinessModel::query()->pluck('name')->all())->toBe(['ArchivumParfums']);
    assertDatabaseCount('users', 1);
    assertDatabaseCount('members', 1);
});

it('refuses a repeated owner email in spanish and leaves no business behind', function () {
    createBusiness(createBusinessArguments(['--password' => 'clave-segura']))->assertExitCode(0);

    createBusiness(createBusinessArguments([
        'name' => 'Esencias del Norte',
        'slug' => 'esencias-del-norte',
        'owner-email' => 'DUENA@archivumparfums.test',
        '--password' => 'clave-segura',
    ]))
        ->expectsOutputToContain('Ya existe una cuenta con ese correo.')
        ->assertExitCode(1);

    expect(BusinessModel::query()->pluck('slug')->all())->toBe(['archivumparfums']);
    assertDatabaseCount('users', 1);
    assertDatabaseCount('members', 1);
});

it('refuses invalid data in spanish and creates nothing', function (array $overrides, string $message) {
    createBusiness(createBusinessArguments(['--password' => 'clave-segura', ...$overrides]))
        ->expectsOutputToContain($message)
        ->assertExitCode(1);

    assertDatabaseCount('businesses', 0);
    assertDatabaseCount('users', 0);
})->with([
    'empty name' => [['name' => '   '], 'El nombre de la tienda es obligatorio y no puede superar los 100 caracteres.'],
    'slug with spaces' => [['slug' => 'archivum parfums'], 'El slug solo puede tener minúsculas, dígitos y guiones, y no puede superar los 100 caracteres.'],
    'malformed email' => [['owner-email' => 'duena.archivumparfums.test'], 'El correo debe ser una dirección válida de 255 caracteres como máximo.'],
    'short password' => [['--password' => '1234567'], 'La contraseña debe tener al menos 8 caracteres.'],
]);

it('refuses a missing password without an interactive terminal', function () {
    $exitCode = Artisan::call(CREATE_BUSINESS, createBusinessArguments(['--no-interaction' => true]));

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('La contraseña debe tener al menos 8 caracteres.');
    assertDatabaseCount('businesses', 0);
    assertDatabaseCount('users', 0);
});

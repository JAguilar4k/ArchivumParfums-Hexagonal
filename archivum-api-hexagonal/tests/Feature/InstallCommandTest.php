<?php

declare(strict_types=1);

use Database\Factories\UserFactory;
use Illuminate\Database\Console\Seeds\SeedCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

/**
 * Run the installer with a disposable environment file and the test database credentials.
 *
 * @param  callable(): void  $assertions
 */
function withLocalInstaller(callable $assertions): void
{
    $previous = [];

    foreach (['APP_ENV', 'CI', 'COMPOSER_DEV_MODE', 'DB_SUPERUSER_PASSWORD'] as $variable) {
        $previous[$variable] = getenv($variable);
    }

    $environment = app()->environmentPath();
    $configuration = config('database.connections');
    $key = config('app.key');
    $demoPassword = config('archivum.demo_password');
    $directory = sys_get_temp_dir().'/archivum-install-'.bin2hex(random_bytes(8));
    mkdir($directory);
    copy(base_path('.env.example'), $directory.'/.env');
    app()->useEnvironmentPath($directory);
    app()['env'] = 'local';
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'archivum.demo_password' => 'install-test-demo',
    ]);
    putenv('APP_ENV=local');
    putenv('CI');
    putenv('COMPOSER_DEV_MODE');
    putenv('DB_SUPERUSER_PASSWORD='.(string) config('database.connections.pgsql_admin.password'));

    try {
        $assertions();
    } finally {
        foreach ($previous as $variable => $value) {
            putenv($value === false ? $variable : "{$variable}={$value}");
        }

        config([
            'database.connections' => $configuration,
            'app.key' => $key,
            'archivum.demo_password' => $demoPassword,
        ]);
        app()->useEnvironmentPath($environment);
        app()['env'] = 'testing';
        DB::purge('pgsql');
        DB::purge('pgsql_admin');
        DB::purge('archivum_superuser');
        DB::purge('installer_original_role');
        unlink($directory.'/.env');
        rmdir($directory);
    }
}

it('runs after every composer install', function () {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect($composer['scripts']['post-install-cmd'] ?? [])->toContain('@php artisan archivum:install --ansi');
});

it('leaves the environment alone outside development', function () {
    expect(Artisan::call('archivum:install'))->toBe(0)
        ->and(Artisan::output())->toContain(__('install.skipped_outside_development'));
});

it('keeps the data and seeds nothing again in a local installation', function () {
    withLocalInstaller(function (): void {
        expect(Artisan::call('archivum:install'))->toBe(0);
        DB::table('users')->update(['name' => 'An edited demo account']);
        $before = DB::table('users')->orderBy('id')->get()->toArray();

        expect(Artisan::call('archivum:install'))->toBe(0)
            ->and(Artisan::output())->toContain(__('install.ready'));

        expect(DB::table('users')->orderBy('id')->get()->toArray())->toEqual($before);
    });
});

it('requires the current password of an existing role without changing it', function (string $connection, string $variable) {
    withLocalInstaller(function () use ($connection, $variable): void {
        config(['database.connections.installer_original_role' => config("database.connections.{$connection}")]);
        config(["database.connections.{$connection}.password" => '']);
        config(['database.connections.pgsql.database' => 'archivum_install_missing_database']);
        DB::purge('pgsql');
        DB::purge($connection);
        $before = file_get_contents(app()->environmentFilePath());

        expect(Artisan::call('archivum:install', ['--superuser' => config('database.connections.pgsql_admin.username')]))
            ->toBe(1);

        expect(Artisan::output())->toContain(__('install.existing_role_password', ['variable' => $variable]));
        expect(file_get_contents(app()->environmentFilePath()))->toBe($before);
        expect(DB::connection('installer_original_role')->scalar('select 1'))->toBe(1);
    });
})->with([
    'owner role' => ['pgsql_admin', 'DB_ADMIN_PASSWORD'],
    'application role' => ['pgsql', 'DB_PASSWORD'],
]);

it('seeds a database whose migrations were already applied', function () {
    withLocalInstaller(function (): void {
        expect(Artisan::call('archivum:install'))->toBe(0);

        expect(DB::table('users')->count())->toBeGreaterThan(0);
    });
});

it('rolls back a failed seed and completes it on the next installation', function () {
    Artisan::command('db:seed {--force}', function (): int {
        UserFactory::new()->create(['email' => 'partial@example.com']);

        return 1;
    });

    withLocalInstaller(function (): void {
        expect(Artisan::call('archivum:install'))->toBe(1);
        expect(Artisan::output())->toContain(__('install.seeding_failed'));
        $this->assertDatabaseCount('users', 0);
        Artisan::registerCommand(new SeedCommand(app('db')));

        expect(Artisan::call('archivum:install'))->toBe(0);

        expect(DB::table('users')->count())->toBeGreaterThan(0);
        $this->assertDatabaseMissing('users', ['email' => 'partial@example.com']);
    });
});

it('reports a failed migration without seeding or announcing success', function () {
    Artisan::command('migrate {--database=} {--force}', fn (): int => 1);

    withLocalInstaller(function (): void {
        expect(Artisan::call('archivum:install'))->toBe(1);
        expect(Artisan::output())->toContain(__('install.migration_failed'))->not->toContain(__('install.ready'));
        $this->assertDatabaseCount('users', 0);
    });
});

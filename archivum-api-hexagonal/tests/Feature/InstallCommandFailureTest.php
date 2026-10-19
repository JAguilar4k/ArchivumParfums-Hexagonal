<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Run a failed local installation without reading or writing a real environment file.
 *
 * @param  callable(): void  $assertions
 */
function withInstallerFailureEnvironment(callable $assertions): void
{
    $previous = [];

    foreach (['APP_ENV', 'CI', 'COMPOSER_DEV_MODE', 'DB_SUPERUSER_PASSWORD'] as $variable) {
        $previous[$variable] = getenv($variable);
    }

    $environmentPath = app()->environmentPath();
    $environment = app()->environment();
    $key = config('app.key');
    $directory = sys_get_temp_dir().'/archivum-install-failure-'.bin2hex(random_bytes(8));
    mkdir($directory);
    file_put_contents($directory.'/.env', 'APP_ENV=local'.PHP_EOL);
    app()->useEnvironmentPath($directory);
    app()['env'] = 'local';
    config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    putenv('APP_ENV=local');
    putenv('CI');
    putenv('COMPOSER_DEV_MODE');
    putenv('DB_SUPERUSER_PASSWORD=fictitious-superuser-password');

    try {
        $assertions();
    } finally {
        foreach ($previous as $variable => $value) {
            putenv($value === false ? $variable : "{$variable}={$value}");
        }

        config(['app.key' => $key]);
        app()->useEnvironmentPath($environmentPath);
        app()['env'] = $environment;
        unlink($directory.'/.env');
        rmdir($directory);
    }
}

it('reports a failed query without exposing its sql, bindings or driver details', function (string $locale, string $sqlstate, string $diagnostic): void {
    app()->setLocale($locale);
    $literalPassword = 'fictitious-role-password-215';
    $boundPassword = 'fictitious-bound-password-215';
    $driverPassword = 'fictitious-driver-password-215';
    $driverError = new PDOException('Server detail containing '.$driverPassword);
    $driverError->errorInfo = [$sqlstate, 7, $driverError->getMessage()];
    $exception = new QueryException(
        'archivum_superuser',
        "CREATE ROLE fixture LOGIN PASSWORD '{$literalPassword}'; SELECT ?",
        [$boundPassword],
        $driverError,
    );

    DB::shouldReceive('connection')->once()->with('pgsql')->andThrow(new PDOException('Unavailable application connection.'));
    DB::shouldReceive('connection')->once()->with('archivum_superuser')->andThrow($exception);

    withInstallerFailureEnvironment(function () use ($diagnostic, $literalPassword, $boundPassword, $driverPassword): void {
        expect(Artisan::call('archivum:install'))->toBe(1);

        expect(Artisan::output())->toContain($diagnostic)
            ->not->toContain('CREATE ROLE', 'SQL:', $literalPassword, $boundPassword, $driverPassword, 'fictitious-invalid-state');
    });
})->with([
    'Spanish SQLSTATE' => ['es', '42501', 'Falló una operación de PostgreSQL (SQLSTATE 42501).'],
    'English SQLSTATE' => ['en', '42501', 'A PostgreSQL operation failed (SQLSTATE 42501).'],
    'Spanish untrusted state' => ['es', 'fictitious-invalid-state', 'Falló una operación de PostgreSQL.'],
    'English untrusted state' => ['en', 'fictitious-invalid-state', 'A PostgreSQL operation failed.'],
]);

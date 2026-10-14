<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process as SymfonyProcess;
use Throwable;

/**
 * Leave a fresh clone ready to work, one step after the other: .env, application key,
 * PostgreSQL roles and databases, migrations and demo data.
 *
 * Composer runs it after every `composer install`. Each step is skipped when it is
 * already done, so running it again never loses data.
 */
#[Signature('archivum:install {--superuser=postgres : PostgreSQL superuser that creates the roles and databases}')]
final class InstallCommand extends Command
{
    /** Describe the command for the command list. */
    public function __construct()
    {
        parent::__construct();

        $this->setDescription(__('install.description'));
    }

    /** Run every step that is still missing, answering 0 when the environment is ready. */
    public function handle(): int
    {
        if ($this->runsOutsideDevelopment()) {
            $this->components->info(__('install.skipped_outside_development'));

            return self::SUCCESS;
        }

        if (! file_exists($this->laravel->environmentFilePath())) {
            copy($this->laravel->basePath('.env.example'), $this->laravel->environmentFilePath());
            $this->components->info(__('install.environment_file_created'));

            return $this->runAgainWithTheNewEnvironmentFile();
        }

        if (! $this->laravel->environment('local')) {
            $this->components->info(__('install.skipped_environment', ['environment' => $this->laravel->environment()]));

            return self::SUCCESS;
        }

        $this->generateApplicationKey();

        try {
            if (! $this->databasesAreReachable()) {
                $password = $this->superuserPassword();

                if ($password === null) {
                    $this->components->warn(__('install.superuser_needed', ['user' => $this->superuser()]));

                    return self::FAILURE;
                }

                $this->createRolesAndDatabases($this->superuserConnection($password));
            }

            $this->migrateAndSeed();
        } catch (Throwable $exception) {
            $this->components->error(__('install.database_failed', ['message' => $exception->getMessage()]));
            $this->line(__('install.database_hint'));

            return self::FAILURE;
        }

        $this->components->info(__('install.ready'));

        return self::SUCCESS;
    }

    /**
     * Continuous integration prepares its own environment, `composer install --no-dev` is a
     * production install, and the tests or a server set APP_ENV before .env exists.
     */
    private function runsOutsideDevelopment(): bool
    {
        $environment = getenv('APP_ENV');

        return in_array(getenv('CI'), ['true', '1'], true)
            || getenv('COMPOSER_DEV_MODE') === '0'
            || ($environment !== false && $environment !== 'local');
    }

    /** The running process booted without .env, so a new one reads it from the start. */
    private function runAgainWithTheNewEnvironmentFile(): int
    {
        $arguments = [PHP_BINARY, 'artisan', 'archivum:install', '--ansi', '--superuser='.$this->superuser()];

        $result = Process::forever()
            ->path($this->laravel->basePath())
            ->tty(SymfonyProcess::isTtySupported())
            ->run($arguments, function (string $type, string $output): void {
                $this->output->write($output);
            });

        return $result->exitCode() ?? self::FAILURE;
    }

    private function generateApplicationKey(): void
    {
        if (filled(config('app.key'))) {
            return;
        }

        $this->call('key:generate', ['--force' => true]);
    }

    /** Both roles log in with the passwords of .env and the testing database exists. */
    private function databasesAreReachable(): bool
    {
        try {
            DB::connection('pgsql')->getPdo();

            return DB::connection('pgsql_admin')->scalar(
                'select count(*) from pg_database where datname = ?',
                [$this->testingDatabase()],
            ) === 1;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Do what database/roles.sql does, without failing on what already exists. A role
     * password missing from .env is generated and written there.
     */
    private function createRolesAndDatabases(Connection $superuser): void
    {
        $owner = $this->connectionSetting('pgsql_admin', 'username');
        $application = $this->connectionSetting('pgsql', 'username');
        $ownerPassword = $this->passwordInEnvironmentFile('pgsql_admin', 'DB_ADMIN_PASSWORD');
        $applicationPassword = $this->passwordInEnvironmentFile('pgsql', 'DB_PASSWORD');

        $superuser->statement("SET password_encryption = 'scram-sha-256'");

        $this->createOrUpdateRole($superuser, $owner, 'LOGIN', $ownerPassword);
        $this->createOrUpdateRole($superuser, $application, 'LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS', $applicationPassword);

        foreach ([$this->connectionSetting('pgsql', 'database'), $this->testingDatabase()] as $database) {
            $exists = $superuser->scalar('select count(*) from pg_database where datname = ?', [$database]) === 1;

            if (! $exists) {
                $superuser->statement(sprintf('CREATE DATABASE %s OWNER %s', $this->quoted($database), $this->quoted($owner)));
            }

            $superuser->statement(sprintf('REVOKE ALL ON DATABASE %s FROM PUBLIC', $this->quoted($database)));
            $superuser->statement(sprintf('GRANT CONNECT ON DATABASE %s TO %s', $this->quoted($database), $this->quoted($application)));
        }

        foreach (['archivum_superuser', 'pgsql', 'pgsql_admin'] as $connection) {
            DB::purge($connection);
        }

        $this->components->info(__('install.roles_ready', ['owner' => $owner, 'application' => $application]));
    }

    private function createOrUpdateRole(Connection $superuser, string $role, string $attributes, string $password): void
    {
        $exists = $superuser->scalar('select count(*) from pg_roles where rolname = ?', [$role]) === 1;

        $superuser->statement(sprintf(
            '%s ROLE %s %s %s PASSWORD %s',
            $exists ? 'ALTER' : 'CREATE',
            $this->quoted($role),
            $exists ? 'WITH' : '',
            $attributes,
            $superuser->getPdo()->quote($password),
        ));
    }

    /**
     * The password of the PostgreSQL superuser, from the DB_SUPERUSER_PASSWORD variable of
     * the terminal or asked for. It is never saved. Null when it cannot be asked.
     */
    private function superuserPassword(): ?string
    {
        $password = getenv('DB_SUPERUSER_PASSWORD');

        if ($password !== false) {
            return $password;
        }

        if (! $this->canAskQuestions()) {
            return null;
        }

        $this->components->info(__('install.superuser_reason', ['user' => $this->superuser()]));

        return (string) $this->secret(__('install.superuser_password_prompt', ['user' => $this->superuser()]), false);
    }

    /** A connection to the postgres database as the superuser. */
    private function superuserConnection(string $password): Connection
    {
        config(['database.connections.archivum_superuser' => [
            ...(array) config('database.connections.pgsql_admin'),
            'database' => 'postgres',
            'username' => $this->superuser(),
            'password' => $password,
        ]]);

        return DB::connection('archivum_superuser');
    }

    /**
     * Composer on Windows gives its scripts no keyboard, so a question there would get an
     * empty answer.
     */
    private function canAskQuestions(): bool
    {
        return $this->input->isInteractive() && (! function_exists('stream_isatty') || stream_isatty(STDIN));
    }

    private function superuser(): string
    {
        $superuser = $this->option('superuser');

        return is_string($superuser) && $superuser !== '' ? $superuser : 'postgres';
    }

    /** The password of a role in .env, generated and written there when it is empty. */
    private function passwordInEnvironmentFile(string $connection, string $variable): string
    {
        $password = $this->connectionSetting($connection, 'password');

        if ($password !== '') {
            return $password;
        }

        $password = Str::password(32, symbols: false);
        $path = $this->laravel->environmentFilePath();
        $contents = (string) file_get_contents($path);
        $line = "{$variable}={$password}";

        file_put_contents($path, preg_match("/^{$variable}=.*$/m", $contents) === 1
            ? (string) preg_replace("/^{$variable}=.*$/m", $line, $contents)
            : rtrim($contents).PHP_EOL.$line.PHP_EOL);

        config(["database.connections.{$connection}.password" => $password]);

        return $password;
    }

    /** Run the pending migrations with the schema owner and seed the demo data the first time. */
    private function migrateAndSeed(): void
    {
        $migratedBefore = Schema::connection('pgsql_admin')->hasTable('migrations');

        $this->call('migrate', ['--database' => 'pgsql_admin', '--force' => true]);

        if (! $migratedBefore) {
            $this->call('db:seed', ['--force' => true]);
        }
    }

    /** The database the tests use, as phpunit.xml sets it. */
    private function testingDatabase(): string
    {
        $phpunit = simplexml_load_file($this->laravel->basePath('phpunit.xml'));
        $database = $phpunit === false ? [] : $phpunit->xpath('//env[@name="DB_DATABASE"]/@value');

        return is_array($database) && isset($database[0]) ? (string) $database[0] : 'archivum_hexagonal_testing';
    }

    private function connectionSetting(string $connection, string $setting): string
    {
        $value = config("database.connections.{$connection}.{$setting}");

        return is_string($value) ? $value : '';
    }

    /** An identifier quoted for PostgreSQL, such as a role or a database name. */
    private function quoted(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}

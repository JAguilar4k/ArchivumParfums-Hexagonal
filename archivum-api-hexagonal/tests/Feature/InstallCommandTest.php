<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

it('runs after every composer install', function () {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect($composer['scripts']['post-install-cmd'] ?? [])->toContain('@php artisan archivum:install --ansi');
});

it('leaves the environment alone outside development', function () {
    expect(Artisan::call('archivum:install'))->toBe(0)
        ->and(Artisan::output())->toContain(__('install.skipped_outside_development'));
});

it('keeps the data and seeds nothing again in a local installation', function () {
    $previous = ['APP_ENV' => getenv('APP_ENV'), 'CI' => getenv('CI')];
    putenv('APP_ENV=local');
    putenv('CI');
    app()['env'] = 'local';

    $migrations = DB::connection('pgsql_admin')->table('migrations')->count();
    $users = DB::table('users')->count();

    try {
        expect(Artisan::call('archivum:install'))->toBe(0)
            ->and(Artisan::output())->toContain(__('install.ready'))
            ->and(DB::connection('pgsql_admin')->table('migrations')->count())->toBe($migrations)
            ->and(DB::table('users')->count())->toBe($users);
    } finally {
        foreach ($previous as $variable => $value) {
            putenv($value === false ? $variable : "{$variable}={$value}");
        }

        app()['env'] = 'testing';
    }
})->skip(fn (): bool => ! file_exists(app()->environmentFilePath()), 'Needs the .env of a local installation.');

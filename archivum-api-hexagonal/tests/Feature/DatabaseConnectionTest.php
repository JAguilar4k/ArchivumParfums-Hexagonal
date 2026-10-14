<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('uses postgresql as the default connection', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql');
});

it('runs the tests against the testing database', function () {
    expect(DB::connection()->getDatabaseName())->toBe('archivum_hexagonal_testing');
});

it('accepts queries', function () {
    expect(DB::scalar('select 1'))->toBe(1);
});

it('connects to postgresql 18 or newer', function () {
    expect((int) DB::scalar('show server_version_num'))->toBeGreaterThanOrEqual(180000);
});

it('generates uuid version 7 in the database', function () {
    $uuid = DB::scalar('select uuidv7()');

    expect($uuid)->toBeString()
        ->and(Str::isUuid($uuid, 7))->toBeTrue();
});

it('reads the ssl mode from the environment', function () {
    expect(config('database.connections.pgsql.sslmode'))->toBe($_ENV['DB_SSLMODE'] ?? 'prefer');
});

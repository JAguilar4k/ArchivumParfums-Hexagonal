<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

const COSTA_RICA = 'America/Costa_Rica';

it('runs the application on costa rica time', function () {
    expect(config('app.timezone'))->toBe(COSTA_RICA)
        ->and(date_default_timezone_get())->toBe(COSTA_RICA)
        ->and(now()->timezoneName)->toBe(COSTA_RICA);
});

it('runs the database sessions on costa rica time', function (string $connection) {
    expect(DB::connection($connection)->scalar('show timezone'))->toBe(COSTA_RICA);
})->with(['pgsql', 'pgsql_admin']);

it('serializes dates in iso 8601 with the costa rica offset', function () {
    $startsAt = CarbonImmutable::parse('2026-10-21 13:00:00');

    expect($startsAt->toIso8601String())->toBe('2026-10-21T13:00:00-06:00')
        ->and(new JsonResponse(['inicia_en' => $startsAt])->getContent())
        ->toBe('{"inicia_en":"2026-10-21T13:00:00-06:00"}');
});

it('reads back a timestamptz saved with another offset as the same instant', function () {
    $admin = DB::connection('pgsql_admin');
    $admin->statement('create temporary table time_zone_check (at timestamptz not null)');
    $admin->insert('insert into time_zone_check (at) values (?)', ['2026-10-21T21:00:00+02:00']);

    $stored = CarbonImmutable::parse($admin->scalar('select at from time_zone_check'));

    expect($stored->equalTo(CarbonImmutable::parse('2026-10-21T19:00:00Z')))->toBeTrue()
        ->and($stored->toIso8601String())->toBe('2026-10-21T13:00:00-06:00');
});

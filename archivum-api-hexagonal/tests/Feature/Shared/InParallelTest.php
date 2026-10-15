<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

it('runs each operation in its own process with its own connection', function () {
    $operation = fn (): array => [getmypid(), DB::scalar('select pg_backend_pid()')];

    [[$firstProcess, $firstConnection], [$secondProcess, $secondConnection]] = inParallel([], $operation, $operation);

    expect([$firstProcess, $secondProcess])->not->toContain(getmypid())
        ->and($firstProcess)->not->toBe($secondProcess)
        ->and([$firstConnection, $secondConnection])->not->toContain(DB::scalar('select pg_backend_pid()'))
        ->and($firstConnection)->not->toBe($secondConnection);
});

it('runs the operations outside the transaction of the test', function () {
    BusinessModel::factory()->create();

    expect(BusinessModel::query()->count())->toBe(1)
        ->and(inParallel([], fn (): mixed => DB::scalar('select count(*) from businesses')))->toBe([0]);
});

it('lets only one of two conditional updates of the same row take effect', function () {
    $id = (string) DB::connection('pgsql_admin')->table('businesses')->insertGetId([
        'name' => 'ArchivumParfums',
        'slug' => 'archivumparfums',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Each process renames the business only if nobody renamed it first, and keeps the row locked a moment.
    $rename = function (string $name) use ($id): Closure {
        return function () use ($id, $name): int {
            return DB::transaction(function () use ($id, $name): int {
                $renamed = DB::update('update businesses set name = ? where id = ? and name = ?', [$name, $id, 'ArchivumParfums']);
                DB::select('select pg_sleep(0.3)');

                return $renamed;
            });
        };
    };

    $renamed = inParallel(['businesses'], $rename('Archivum Norte'), $rename('Archivum Sur'));

    expect($renamed)->toEqualCanonicalizing([0, 1])
        ->and(DB::connection('pgsql_admin')->table('businesses')->where('id', $id)->value('name'))
        ->toBe($renamed[0] === 1 ? 'Archivum Norte' : 'Archivum Sur');
});

it('leaves no row behind for the next test', function () {
    expect(DB::connection('pgsql_admin')->table('businesses')->count())->toBe(0);
});

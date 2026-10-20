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

    // The winner keeps its row locked until PostgreSQL confirms the other operation is waiting on it.
    $rename = function (string $name) use ($id): Closure {
        return function () use ($id, $name): array {
            return DB::transaction(function () use ($id, $name): array {
                $renamed = DB::update('update businesses set name = ? where id = ? and name = ?', [$name, $id, 'ArchivumParfums']);
                $overlapped = false;

                if ($renamed === 1) {
                    $deadline = microtime(true) + 10;

                    do {
                        // PostgreSQL keeps one picture of pg_stat_activity per transaction; a fresh one shows a session that connected later.
                        DB::statement('select pg_stat_clear_snapshot()');
                        $overlapped = (bool) DB::scalar(<<<'SQL'
                            select exists (
                                select 1 from pg_stat_activity
                                where datname = current_database()
                                  and pg_backend_pid() = any (pg_blocking_pids(pid))
                            )
                            SQL);

                        if (! $overlapped) {
                            if (microtime(true) >= $deadline) {
                                throw new RuntimeException('The competing update never waited on the locked row.');
                            }

                            usleep(10_000);
                        }
                    } while (! $overlapped);
                }

                return ['renamed' => $renamed, 'overlapped' => $overlapped];
            });
        };
    };

    $results = inParallel(['businesses'], $rename('Archivum Norte'), $rename('Archivum Sur'));
    $renamed = array_column($results, 'renamed');

    expect($renamed)->toEqualCanonicalizing([0, 1])
        ->and(array_column($results, 'overlapped'))->toEqualCanonicalizing([true, false])
        ->and(DB::connection('pgsql_admin')->table('businesses')->where('id', $id)->value('name'))
        ->toBe($renamed[0] === 1 ? 'Archivum Norte' : 'Archivum Sur');
});

it('cleans up its barrier when an operation fails', function () {
    $before = glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'archivum-parallel-*') ?: [];
    $failure = static fn () => throw new RuntimeException('Parallel operation failed.');
    $success = static fn (): bool => true;

    expect(fn () => inParallel([], $failure, $success))
        ->toThrow(RuntimeException::class, 'Parallel operation failed.');

    expect(glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'archivum-parallel-*') ?: [])->toBe($before);
});

it('leaves no row behind for the next test', function () {
    expect(DB::connection('pgsql_admin')->table('businesses')->count())->toBe(0);
});

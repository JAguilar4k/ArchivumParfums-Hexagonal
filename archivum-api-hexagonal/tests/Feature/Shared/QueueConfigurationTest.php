<?php

declare(strict_types=1);

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

// The locks of the cache commit on their own connection, outside the transaction of the test.
afterEach(function (): void {
    DB::connection('pgsql_locks')->table('cache_locks')->delete();
});

it('keeps the jobs in the database and writes each one only after its transaction commits', function (): void {
    expect(config('queue.connections.database.driver'))->toBe('database')
        ->and(config('queue.connections.database.table'))->toBe('jobs')
        ->and(config('queue.connections.database.after_commit'))->toBeTrue()
        ->and((string) file_get_contents(base_path('.env.example')))->toMatch('/^QUEUE_CONNECTION=database$/m');
});

it('keeps a job that runs out of attempts in failed_jobs with its own identifier', function (): void {
    expect(config('queue.failed.driver'))->toBe('database-uuids')
        ->and(config('queue.failed.table'))->toBe('failed_jobs');
});

it('lets the application role use the tables of the queue that the owner migrated', function (string $table): void {
    $table = (array) DB::selectOne(
        "select tableowner, has_table_privilege(current_user, tablename, 'select, insert, update, delete') as granted
           from pg_tables where schemaname = 'public' and tablename = ?",
        [$table],
    );

    expect($table)->toBe(['tableowner' => config('database.connections.pgsql_admin.username'), 'granted' => true]);
})->with(['jobs', 'job_batches', 'failed_jobs', 'cache_locks']);

/** A lock of the database store of the cache, the one the unique jobs take. */
function databaseCacheLock(string $name): Lock
{
    $store = Cache::store('database')->getStore();
    assert($store instanceof LockProvider);

    return $store->lock($name);
}

it('answers that a lock is held, inside a transaction, without breaking the transaction', function (): void {
    expect(config('cache.stores.database.lock_connection'))->toBe('pgsql_locks')
        ->and(databaseCacheLock('held')->get())->toBeTrue();

    // On the connection of the transaction, the failed INSERT of the lock would abort it (SQLSTATE 25P02).
    $answer = DB::transaction(fn (): array => [
        'lock' => databaseCacheLock('held')->get(),
        'transaction' => DB::scalar('select 1'),
    ]);

    expect($answer)->toBe(['lock' => false, 'transaction' => 1]);
});

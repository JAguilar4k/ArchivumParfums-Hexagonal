<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\RecordingJob;

use function Pest\Laravel\artisan;

pest()->use(RefreshDatabaseAsOwner::class);

// The queue and the cache of production: the database queue, and the locks of the unique jobs in cache_locks.
beforeEach(function (): void {
    config(['queue.default' => 'database', 'cache.default' => 'database']);
    RecordingJob::reset();
});

// The locks commit on their own connection, outside the transaction of the test.
afterEach(function (): void {
    DB::connection('pgsql_locks')->table('cache_locks')->delete();
});

/** Run one job of the database queue, as the worker of production does. */
function workTheNextUniqueJob(): void
{
    $worker = artisan('queue:work', ['connection' => 'database', '--once' => true]);
    assert($worker instanceof PendingCommand);
    $worker->assertSuccessful();
}

it('R46: keeps one copy waiting when the same work is dispatched twice, and frees its key once it ran', function (): void {
    $business = StaffedBusiness::create()->business->id;

    RecordingJob::dispatch($business, 'same-work');
    RecordingJob::dispatch($business, 'same-work');
    RecordingJob::dispatch($business, 'other-work');

    expect(DB::table('jobs')->count())->toBe(2)
        ->and(DB::connection('pgsql_locks')->table('cache_locks')->count())->toBe(2);

    workTheNextUniqueJob();
    workTheNextUniqueJob();
    RecordingJob::dispatch($business, 'same-work');

    expect(RecordingJob::$attempts)->toHaveCount(2)
        ->and(DB::table('jobs')->count())->toBe(1);
});

it('R46: keeps the key of a job that waits for a new attempt', function (): void {
    $business = StaffedBusiness::create()->business->id;
    RecordingJob::reset(failures: 1);

    RecordingJob::dispatch($business, 'retried-work');
    workTheNextUniqueJob();
    RecordingJob::dispatch($business, 'retried-work');

    expect(DB::table('jobs')->sole()->attempts)->toBe(1);
});

it('writes the job only when the transaction that dispatched it commits', function (): void {
    $business = StaffedBusiness::create()->business->id;

    DB::transaction(function () use ($business): void {
        RecordingJob::dispatch($business, 'committed-work');

        expect(DB::table('jobs')->count())->toBe(0);
    });

    expect(DB::table('jobs')->count())->toBe(1);
});

it('leaves no job and frees its key when the transaction that dispatched it rolls back', function (): void {
    $business = StaffedBusiness::create()->business->id;

    expect(fn () => DB::transaction(function () use ($business): void {
        RecordingJob::dispatch($business, 'rolled-back-work');

        throw new RuntimeException('The operation failed.');
    }))->toThrow(RuntimeException::class, 'The operation failed.');

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::connection('pgsql_locks')->table('cache_locks')->count())->toBe(0);

    RecordingJob::dispatch($business, 'rolled-back-work');

    expect(DB::table('jobs')->count())->toBe(1);
});

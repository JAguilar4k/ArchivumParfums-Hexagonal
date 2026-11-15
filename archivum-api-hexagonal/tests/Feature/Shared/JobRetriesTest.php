<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\RecordingJob;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travel;
use function Pest\Laravel\travelTo;

pest()->use(RefreshDatabaseAsOwner::class);

// The database queue of production instead of the sync one of the tests, worked one job at a time.
beforeEach(function (): void {
    config(['queue.default' => 'database']);
    travelTo('2026-11-08 12:00:00');
    RecordingJob::reset();
});

/** Run one job of the database queue, as the worker of production does. */
function workTheNextRetriedJob(): void
{
    $worker = artisan('queue:work', ['connection' => 'database', '--once' => true]);
    assert($worker instanceof PendingCommand);
    $worker->assertSuccessful();
}

it('R46: sends a job that fails back to the queue with the next wait of config/archivum.php', function (): void {
    RecordingJob::reset(failures: PHP_INT_MAX);
    RecordingJob::dispatch(StaffedBusiness::create()->business->id);

    $waits = [];
    foreach ([1, 2, 3, 4] as $attempt) {
        workTheNextRetriedJob();
        $job = DB::table('jobs')->sole();
        expect($job->attempts)->toBe($attempt);
        $waits[] = $job->available_at - now()->getTimestamp();
        travel(end($waits))->seconds();
    }

    // The last wait repeats until the attempts run out.
    expect($waits)->toBe([10, 60, 300, 300])
        ->and(config('archivum.queue'))->toBe(['tries' => 5, 'backoff' => [10, 60, 300]]);
});

it('R46: keeps a job that runs out of attempts in failed_jobs, from where php artisan queue:retry sends it again', function (): void {
    RecordingJob::reset(failures: 5);
    RecordingJob::dispatch(StaffedBusiness::create()->business->id);

    foreach ([10, 60, 300, 300, 0] as $wait) {
        workTheNextRetriedJob();
        travel($wait)->seconds();
    }

    expect(RecordingJob::$attempts)->toHaveCount(5)
        ->and(DB::table('jobs')->count())->toBe(0);
    $failed = DB::table('failed_jobs')->sole();
    expect($failed->payload)->toContain('RecordingJob')
        ->and($failed->exception)->toContain('RuntimeException: The provider did not answer.');

    $retry = artisan('queue:retry', ['id' => [$failed->uuid]]);
    assert($retry instanceof PendingCommand);
    $retry->assertSuccessful()->run();
    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(1);

    workTheNextRetriedJob();

    expect(RecordingJob::$attempts)->toHaveCount(6)
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Infrastructure\Queue\ScopeJobToBusiness;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\RecordingJob;

use function Pest\Laravel\artisan;

pest()->use(RefreshDatabaseAsOwner::class);

// The database queue of production, worked one job at a time.
beforeEach(function (): void {
    config(['queue.default' => 'database']);
    RecordingJob::reset();
});

/** Run one job of the database queue, as the worker of production does. */
function workTheNextScopedJob(): void
{
    $worker = artisan('queue:work', ['connection' => 'database', '--once' => true]);
    assert($worker instanceof PendingCommand);
    $worker->assertSuccessful();
}

it('runs the job of a business in one transaction pinned to it, where only its rows are visible', function (): void {
    $business = StaffedBusiness::create();
    $other = StaffedBusiness::create();
    $outside = DB::transactionLevel();

    RecordingJob::dispatch($business->business->id);
    RecordingJob::dispatch($other->business->id, 'work-of-the-other-business');
    workTheNextScopedJob();
    workTheNextScopedJob();

    expect(RecordingJob::$attempts)->toBe([
        ['tienda' => $business->business->id, 'fijada' => $business->business->id, 'transacciones' => $outside + 1, 'clientes' => [$business->business->id]],
        ['tienda' => $other->business->id, 'fijada' => $other->business->id, 'transacciones' => $outside + 1, 'clientes' => [$other->business->id]],
    ]);
});

it('binds the business of the job and leaves the transactions to the use case in context mode', function (): void {
    $business = StaffedBusiness::create();
    $outside = DB::transactionLevel();

    RecordingJob::dispatch($business->business->id, 'work-in-context', ScopeJobToBusiness::CONTEXT);
    workTheNextScopedJob();

    // Outside a transaction of the business, no row of any business is visible.
    expect(RecordingJob::$attempts)->toBe([
        ['tienda' => $business->business->id, 'fijada' => '', 'transacciones' => $outside, 'clientes' => []],
    ]);
});

it('gives the worker its previous context back after each job', function (): void {
    RecordingJob::dispatch(StaffedBusiness::create()->business->id);
    workTheNextScopedJob();

    expect(fn () => app(BusinessContext::class)->current())
        ->toThrow(LogicException::class, 'The business is unknown outside a route group with the business middleware.');
});

it('fails a job without a business at once, without running it or trying again', function (?string $business): void {
    RecordingJob::dispatch($business);
    workTheNextScopedJob();

    // The job never reached handle(), so it read and wrote nothing.
    expect(RecordingJob::$attempts)->toBe([])
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->sole()->exception)->toContain('LogicException: The job carries no business.');
})->with(['sin tienda' => null, 'vacía' => '', 'que no es un identificador' => 'tienda-1']);

<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Queue;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Base of every job of the queue (#156). Its key keeps one copy waiting: while a copy has not finished, dispatching
 * another with the same key leaves nothing new in the queue. Running a copy twice changes nothing either, because each
 * use case only changes what is still pending, with conditional UPDATEs and unique keys; the key only avoids repeated
 * work. A failed attempt goes back to the queue after the waits of config/archivum.php, and a job that runs out of
 * attempts is kept in failed_jobs. The queue writes the job only after the transaction that dispatched it commits
 * (config/queue.php), so a job of an operation that rolled back never exists.
 */
abstract class IdempotentJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Key of the work the job does, such as the notice or the refund it applies. */
    abstract public function uniqueId(): string;

    /** Attempts before the job is recorded as failed. */
    public function tries(): int
    {
        return (int) config('archivum.queue.tries');
    }

    /**
     * Seconds to wait before the second, third and later attempts; the last wait repeats.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return array_values(array_map(intval(...), (array) config('archivum.queue.backoff')));
    }
}

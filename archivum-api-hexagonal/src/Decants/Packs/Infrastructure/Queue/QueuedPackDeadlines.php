<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Queue;

use DateTimeImmutable;
use Src\Decants\Packs\Application\Contracts\PackDeadlines;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;
use Src\Decants\Packs\Domain\ValueObjects\ExpiryReason;
use Src\Decants\Packs\Infrastructure\Jobs\ExpireDecantPackJob;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class QueuedPackDeadlines implements PackDeadlines
{
    /**
     * Leave in the queue a job that only becomes available at the deadline, even months later: the database queue keeps
     * it until then (available_at). The queue writes it once the transaction that bought or activated the pack commits,
     * so an operation that rolled back leaves no job (#156).
     */
    public function checkAt(DecantPackId $packId, BusinessId $businessId, DateTimeImmutable $moment, ExpiryReason $reason): void
    {
        ExpireDecantPackJob::dispatch($packId->value, $businessId->value, $reason)->delay($moment);
    }
}

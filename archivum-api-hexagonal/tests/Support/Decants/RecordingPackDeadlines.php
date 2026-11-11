<?php

declare(strict_types=1);

namespace Tests\Support\Decants;

use DateTimeImmutable;
use Src\Decants\Packs\Application\Contracts\PackDeadlines;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;
use Src\Decants\Packs\Domain\ValueObjects\ExpiryReason;
use Src\Shared\Domain\ValueObjects\BusinessId;

/** The deadlines of the packs that the test records, instead of leaving their jobs in the queue. */
final class RecordingPackDeadlines implements PackDeadlines
{
    /** @var list<array{pack: string, business: string, at: string, reason: ExpiryReason}> the checks asked for, in order */
    public private(set) array $checks = [];

    public function checkAt(DecantPackId $packId, BusinessId $businessId, DateTimeImmutable $moment, ExpiryReason $reason): void
    {
        $this->checks[] = ['pack' => $packId->value, 'business' => $businessId->value, 'at' => $moment->format(DATE_ATOM), 'reason' => $reason];
    }
}

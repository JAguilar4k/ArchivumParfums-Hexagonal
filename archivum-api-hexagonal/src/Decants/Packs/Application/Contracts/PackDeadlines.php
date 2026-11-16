<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\Contracts;

use DateTimeImmutable;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;
use Src\Decants\Packs\Domain\ValueObjects\ExpiryReason;
use Src\Shared\Domain\ValueObjects\BusinessId;

interface PackDeadlines
{
    /**
     * Check at the given moment whether the pack still has the status the reason expires, and expire it if so: unpaid
     * at its payment deadline, or active at the end of its validity (#159).
     */
    public function checkAt(DecantPackId $packId, BusinessId $businessId, DateTimeImmutable $moment, ExpiryReason $reason): void;
}

<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Domain\Contracts;

use DateTimeImmutable;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;

interface DecantPackBalance
{
    /**
     * Take one decant of a pack of the business of the operation that is active, still valid at that moment and has
     * decants left, exhausting it with its last decant, in one statement (R34). The row stays locked until the
     * transaction ends. Answer whether the decant was taken.
     */
    public function take(DecantPackId $id, DateTimeImmutable $at): bool;
}

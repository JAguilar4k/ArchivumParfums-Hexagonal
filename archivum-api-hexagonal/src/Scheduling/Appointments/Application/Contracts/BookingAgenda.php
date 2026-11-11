<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\Contracts;

use DateTimeImmutable;

/** Port that counts the appointments each person already has in a range, which is what R15 balances. */
interface BookingAgenda
{
    /**
     * The active appointments of each person of the range, keyed by the identity of the member.
     *
     * @param  list<string>  $memberIds
     * @return array<string, int>
     */
    public function countsFor(DateTimeImmutable $from, DateTimeImmutable $to, array $memberIds): array;
}

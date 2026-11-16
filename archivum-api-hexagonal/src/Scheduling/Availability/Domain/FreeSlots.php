<?php

declare(strict_types=1);

namespace Src\Scheduling\Availability\Domain;

use Src\Scheduling\Availability\Domain\ValueObjects\FreeSlot;
use Src\Scheduling\Availability\Domain\ValueObjects\MinuteRange;
use Src\Scheduling\Availability\Domain\ValueObjects\PersonDay;

/**
 * Pure calculation of the free slots of one day of one store (R13). It knows nothing of the database: the reader brings
 * the tramos, the blocks and the active appointments, and this class only intersects and subtracts them.
 *
 * A slot is free for a person when [start, end) fits in the intersection of the store hours and their own hours, does
 * not cross one of their blocks or a block of the whole store, and [start, end + margin) does not cross the
 * [starts_at, blocked_until) of one of their active appointments, the same rule the exclusion constraint of #59 keeps.
 */
final class FreeSlots
{
    /** Every slot starts on a quarter of the clock, as R13 asks. */
    public const int STEP_MINUTES = 15;

    /**
     * Calculate the slots of the day, each one with the people who can attend it.
     *
     * @param  list<MinuteRange>  $businessHours  The tramos the store opens that weekday.
     * @param  list<PersonDay>  $people  The people who attend appointments and work that day.
     * @return list<FreeSlot>
     */
    public static function of(
        array $businessHours,
        array $people,
        int $durationMinutes,
        int $bufferMinutes,
        int $earliestStartMinute,
        int $stepMinutes = self::STEP_MINUTES,
    ): array {
        /** @var array<int, array<string, PersonDay>> $byStart */
        $byStart = [];

        foreach ($people as $person) {
            foreach (self::windowsOf($businessHours, $person->workingHours) as $window) {
                for ($start = self::ceilToStep($window->start, $stepMinutes); $start + $durationMinutes <= $window->end; $start += $stepMinutes) {
                    if ($start < $earliestStartMinute) {
                        continue;
                    }

                    if (self::crossesAny(new MinuteRange($start, $start + $durationMinutes), $person->blocks)) {
                        continue;
                    }

                    $blocked = new MinuteRange($start, $start + $durationMinutes + $bufferMinutes);

                    if (self::crossesAny($blocked, $person->appointments)) {
                        continue;
                    }

                    $byStart[$start][$person->memberId] = $person;
                }
            }
        }

        ksort($byStart);

        $slots = [];

        foreach ($byStart as $start => $available) {
            $slots[] = new FreeSlot(new MinuteRange($start, $start + $durationMinutes), self::orderedByName($available));
        }

        return $slots;
    }

    /**
     * The minutes of the day where the store and the person are both open.
     *
     * @param  list<MinuteRange>  $businessHours
     * @param  list<MinuteRange>  $workingHours
     * @return list<MinuteRange>
     */
    private static function windowsOf(array $businessHours, array $workingHours): array
    {
        $windows = [];

        foreach ($businessHours as $business) {
            foreach ($workingHours as $personal) {
                $start = max($business->start, $personal->start);
                $end = min($business->end, $personal->end);

                if ($end > $start) {
                    $windows[] = new MinuteRange($start, $end);
                }
            }
        }

        return $windows;
    }

    /** First quarter of the clock at or after the minute the window opens. */
    private static function ceilToStep(int $minute, int $stepMinutes): int
    {
        return intdiv($minute + $stepMinutes - 1, $stepMinutes) * $stepMinutes;
    }

    /**
     * @param  list<MinuteRange>  $ranges
     */
    private static function crossesAny(MinuteRange $candidate, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if ($candidate->overlaps($range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, PersonDay>  $people
     * @return list<PersonDay>
     */
    private static function orderedByName(array $people): array
    {
        $ordered = array_values($people);

        usort($ordered, fn (PersonDay $left, PersonDay $right): int => [$left->name, $left->memberId] <=> [$right->name, $right->memberId]);

        return $ordered;
    }
}

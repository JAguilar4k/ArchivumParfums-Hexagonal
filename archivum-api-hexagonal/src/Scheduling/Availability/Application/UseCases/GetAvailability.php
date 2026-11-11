<?php

declare(strict_types=1);

namespace Src\Scheduling\Availability\Application\UseCases;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Validation\ValidationException;
use Src\Scheduling\Availability\Application\Contracts\AvailabilityReader;
use Src\Scheduling\Availability\Application\DTOs\AvailablePerson;
use Src\Scheduling\Availability\Application\DTOs\AvailableSlot;
use Src\Scheduling\Availability\Application\DTOs\BusyRange;
use Src\Scheduling\Availability\Application\DTOs\PersonAvailability;
use Src\Scheduling\Availability\Domain\FreeSlots;
use Src\Scheduling\Availability\Domain\ValueObjects\FreeSlot;
use Src\Scheduling\Availability\Domain\ValueObjects\MinuteRange;
use Src\Scheduling\Availability\Domain\ValueObjects\PersonDay;
use Src\Scheduling\Services\Domain\ValueObjects\ServiceId;
use Src\Scheduling\Shared\Domain\ValueObjects\TimeRange;
use Src\Scheduling\Shared\Domain\ValueObjects\Weekday;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\MemberId;

/**
 * Calculate the free slots of one service of one store on one local day (R13). The visitor chooses the store, the
 * service and, when they want, the person; without a person the answer lists who can attend each slot.
 */
final readonly class GetAvailability
{
    /** Receive the reader that brings the data of the day. */
    public function __construct(private AvailabilityReader $reader) {}

    /**
     * The free slots of the day, ordered from the earliest; a day outside the booking window has none. The appointment
     * being moved is left out of the calculation, so its own range does not block the new one (#65).
     *
     * @return list<AvailableSlot>
     *
     * @throws ValidationException when the service is not bookable or the person does not attend appointments here
     */
    public function execute(string $serviceId, string $date, ?string $memberId = null, ?string $exceptAppointment = null): array
    {
        $store = $this->reader->store();
        $timezone = new DateTimeZone($store->timezone);
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);

        if ($day === false) {
            throw ValidationException::withMessages(['fecha' => __('scheduling.availability.invalid_date')]);
        }

        $now = new DateTimeImmutable('now', $timezone);
        $today = $now->setTime(0, 0);

        // A day before today or beyond the horizon is a valid date: the store simply offers nothing on it.
        if ($day < $today || $day > $today->modify("+{$store->bookingHorizonDays} days")) {
            return [];
        }

        $weekday = Weekday::from((int) $day->format('N'));
        $service = $this->reader->service($this->serviceId($serviceId));

        if ($service === null) {
            throw ValidationException::withMessages(['servicio' => __('scheduling.availability.service_not_bookable')]);
        }

        $only = $memberId === null ? null : $this->memberId($memberId);
        $people = $this->reader->people($weekday, $day, $day->modify('+1 day'), $only, $exceptAppointment);

        if ($only !== null && $people === []) {
            throw ValidationException::withMessages(['persona' => __('scheduling.availability.person_not_available')]);
        }

        $slots = FreeSlots::of(
            $this->minuteRanges($this->reader->businessHours($weekday)),
            array_map(fn (PersonAvailability $person): PersonDay => $this->personDay($person, $day), $people),
            $service->durationMinutes,
            $service->bufferMinutes,
            $this->minuteOf($day, $now->modify("+{$store->minimumNoticeHours} hours")),
        );

        return array_map(fn (FreeSlot $slot): AvailableSlot => new AvailableSlot(
            $day->modify("+{$slot->range->start} minutes"),
            $day->modify("+{$slot->range->end} minutes"),
            array_map(
                fn (PersonDay $person): AvailablePerson => new AvailablePerson($person->memberId, $person->name),
                $slot->people,
            ),
        ), $slots);
    }

    /**
     * @param  list<TimeRange>  $ranges
     * @return list<MinuteRange>
     */
    private function minuteRanges(array $ranges): array
    {
        return array_map(
            fn (TimeRange $range): MinuteRange => new MinuteRange($range->start->minutes, $range->end->minutes),
            $ranges,
        );
    }

    /** The person as the pure calculation needs them, with every instant counted from the midnight of the day. */
    private function personDay(PersonAvailability $person, DateTimeImmutable $day): PersonDay
    {
        return new PersonDay(
            $person->memberId,
            $person->name,
            $this->minuteRanges($person->workingHours),
            $this->minuteRangesOf($day, $person->blocks),
            $this->minuteRangesOf($day, $person->appointments),
        );
    }

    /**
     * @param  list<BusyRange>  $ranges
     * @return list<MinuteRange>
     */
    private function minuteRangesOf(DateTimeImmutable $day, array $ranges): array
    {
        return array_map(
            fn (BusyRange $range): MinuteRange => new MinuteRange(
                $this->minuteOf($day, $range->startsAt),
                $this->minuteOf($day, $range->endsAt),
            ),
            $ranges,
        );
    }

    /** Minute of the day an instant falls on; negative before the midnight and beyond 1440 after it. */
    private function minuteOf(DateTimeImmutable $day, DateTimeImmutable $instant): int
    {
        return intdiv($instant->getTimestamp() - $day->getTimestamp(), 60);
    }

    /** @throws ValidationException */
    private function serviceId(string $serviceId): ServiceId
    {
        try {
            return new ServiceId($serviceId);
        } catch (InvalidValueException) {
            throw ValidationException::withMessages(['servicio' => __('scheduling.availability.service_not_bookable')]);
        }
    }

    /** @throws ValidationException */
    private function memberId(string $memberId): MemberId
    {
        try {
            return new MemberId($memberId);
        } catch (InvalidValueException) {
            throw ValidationException::withMessages(['persona' => __('scheduling.availability.person_not_available')]);
        }
    }
}

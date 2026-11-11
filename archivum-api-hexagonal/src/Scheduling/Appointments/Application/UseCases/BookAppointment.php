<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\UseCases;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Illuminate\Validation\ValidationException;
use Src\Scheduling\Appointments\Application\Contracts\AppointmentCustomers;
use Src\Scheduling\Appointments\Application\Contracts\BookingAgenda;
use Src\Scheduling\Appointments\Application\Contracts\BookingCatalog;
use Src\Scheduling\Appointments\Application\DTOs\BookAppointmentCommand;
use Src\Scheduling\Appointments\Application\DTOs\BookedAppointment;
use Src\Scheduling\Appointments\Application\DTOs\BookingTerms;
use Src\Scheduling\Appointments\Application\DTOs\PersonOption;
use Src\Scheduling\Appointments\Domain\Contracts\AppointmentRepository;
use Src\Scheduling\Appointments\Domain\Entities\Appointment;
use Src\Scheduling\Appointments\Domain\Exceptions\AppointmentSlotTakenException;
use Src\Scheduling\Appointments\Domain\ValueObjects\PerfumeId;
use Src\Scheduling\Appointments\Domain\ValueObjects\ServiceId;
use Src\Scheduling\Availability\Application\DTOs\AvailablePerson;
use Src\Scheduling\Availability\Application\DTOs\AvailableSlot;
use Src\Scheduling\Availability\Application\UseCases\GetAvailability;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\MemberId;

/**
 * Book a trial appointment of a customer (R12, R13, R15): the slot is checked against the real availability of #61, the
 * person without one is the least busy of the slot, and the exclusion constraint of #59 is the one that finally
 * refuses a second appointment on the same slot.
 */
final readonly class BookAppointment
{
    /** Receive the repository, the ports of the store and the use case that calculates the availability. */
    public function __construct(
        private AppointmentRepository $appointments,
        private BookingCatalog $catalog,
        private BookingAgenda $agenda,
        private AppointmentCustomers $customers,
        private GetAvailability $availability,
        private BusinessContext $business,
    ) {}

    /**
     * Book the appointment and answer it as the API shows it.
     *
     * @throws ValidationException when a field of the request is not bookable
     * @throws AppointmentSlotTakenException when the slot is no longer free
     */
    public function execute(BookAppointmentCommand $command): BookedAppointment
    {
        $terms = $this->catalog->terms();
        $zone = new DateTimeZone($terms->timezone);
        $startsAt = $this->startsAt($command->startsAt);
        $day = $startsAt->setTimezone($zone)->setTime(0, 0);

        $this->assertInsideWindow($startsAt, $day, $terms, $zone);

        $serviceId = $this->serviceId($command->serviceId);
        $service = $this->catalog->service($serviceId);

        if ($service === null) {
            throw ValidationException::withMessages(['servicio_id' => __('scheduling.booking.service_unavailable')]);
        }

        $person = $command->memberId === null
            ? null
            : $this->catalog->person($this->memberId($command->memberId));

        if ($command->memberId !== null && $person === null) {
            throw ValidationException::withMessages(['persona_id' => __('scheduling.booking.person_unavailable')]);
        }

        $perfumes = $this->perfumes($command->perfumes, $service->maxPerfumes);
        $slot = $this->slot($service->id, $day, $command->memberId, $startsAt);
        $person ??= $this->leastBusy($slot->people, $day);

        $appointment = Appointment::book(
            $this->appointments->nextId(),
            $this->business->current(),
            new ServiceId($service->id),
            $this->customers->findOrCreate($command->userId),
            new MemberId($person->id),
            $this->forStorage($startsAt),
            $service->durationMinutes,
            $service->bufferMinutes,
            array_map(fn (string $id): PerfumeId => new PerfumeId($id), array_keys($perfumes)),
            $service->maxPerfumes,
        );

        $this->appointments->save($appointment);

        return new BookedAppointment(
            $appointment->id->value,
            $appointment->status()->value,
            $service->id,
            $service->name,
            $person->id,
            $person->name,
            $appointment->startsAt()->setTimezone($zone),
            $appointment->endsAt()->setTimezone($zone),
            $this->catalog->appointmentPlace(),
            array_map(
                fn (string $id, string $name): array => ['id' => $id, 'name' => $name],
                array_keys($perfumes),
                array_values($perfumes),
            ),
        );
    }

    /**
     * The slot of the availability that starts exactly when the customer asked.
     *
     * @throws AppointmentSlotTakenException when the availability does not offer that start
     */
    private function slot(string $serviceId, DateTimeImmutable $day, ?string $memberId, DateTimeImmutable $startsAt): AvailableSlot
    {
        foreach ($this->availability->execute($serviceId, $day->format('Y-m-d'), $memberId) as $slot) {
            if ($slot->startsAt->getTimestamp() === $startsAt->getTimestamp()) {
                return $slot;
            }
        }

        throw AppointmentSlotTakenException::create();
    }

    /**
     * The person of the slot with the fewest appointments that day (R15); a tie keeps the one who comes first by name.
     *
     * @param  list<AvailablePerson>  $people
     *
     * @throws AppointmentSlotTakenException when the slot lists no person at all
     */
    private function leastBusy(array $people, DateTimeImmutable $day): PersonOption
    {
        $counts = $this->agenda->countsFor(
            $day,
            $day->modify('+1 day'),
            array_map(fn (AvailablePerson $person): string => $person->id, $people),
        );

        $chosen = null;
        $least = PHP_INT_MAX;

        foreach ($people as $person) {
            $count = $counts[$person->id] ?? 0;

            if ($count < $least) {
                $chosen = $person;
                $least = $count;
            }
        }

        return $chosen === null
            ? throw AppointmentSlotTakenException::create()
            : new PersonOption($chosen->id, $chosen->name);
    }

    /**
     * The perfumes the appointment tries, in the order the customer chose, refusing repeats and too many of them.
     *
     * @param  list<string>  $ids
     * @return array<string, string>
     *
     * @throws ValidationException when a perfume is repeated, missing or inactive in the store
     */
    private function perfumes(array $ids, int $maxPerfumes): array
    {
        $unique = array_values(array_unique($ids));

        if (count($unique) !== count($ids)) {
            throw ValidationException::withMessages(['perfumes' => __('scheduling.appointment.repeated_perfume')]);
        }

        if (count($unique) > $maxPerfumes) {
            throw ValidationException::withMessages(['perfumes' => __('scheduling.appointment.too_many_perfumes', ['max' => $maxPerfumes])]);
        }

        $available = $this->catalog->perfumes($unique);

        if (count($available) !== count($unique)) {
            throw ValidationException::withMessages(['perfumes' => __('scheduling.booking.perfume_unavailable')]);
        }

        $ordered = [];

        foreach ($unique as $id) {
            $ordered[$id] = $available[$id];
        }

        return $ordered;
    }

    /** The instant must fall between the notice the store gives and the end of its horizon. */
    private function assertInsideWindow(DateTimeImmutable $startsAt, DateTimeImmutable $day, BookingTerms $terms, DateTimeZone $zone): void
    {
        $now = new DateTimeImmutable('now', $zone);
        $lastDay = $now->setTime(0, 0)->modify("+{$terms->bookingHorizonDays} days");

        if ($startsAt < $now->modify("+{$terms->minimumNoticeHours} hours") || $day > $lastDay) {
            throw ValidationException::withMessages(['inicia_en' => __('scheduling.booking.outside_window')]);
        }
    }

    /** @throws ValidationException when the text is not an ISO 8601 instant with its offset */
    private function startsAt(string $startsAt): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($startsAt);
        } catch (Exception) {
            throw ValidationException::withMessages(['inicia_en' => __('scheduling.booking.invalid_start')]);
        }
    }

    /** The instant as the connection stores it, so the slot compares the same way the reader of #61 reads it. */
    private function forStorage(DateTimeImmutable $instant): DateTimeImmutable
    {
        return $instant->setTimezone(new DateTimeZone((string) config('database.connections.pgsql.timezone', 'UTC')));
    }

    /** @throws ValidationException */
    private function serviceId(string $serviceId): ServiceId
    {
        try {
            return new ServiceId($serviceId);
        } catch (InvalidValueException) {
            throw ValidationException::withMessages(['servicio_id' => __('scheduling.booking.service_unavailable')]);
        }
    }

    /** @throws ValidationException */
    private function memberId(string $memberId): MemberId
    {
        try {
            return new MemberId($memberId);
        } catch (InvalidValueException) {
            throw ValidationException::withMessages(['persona_id' => __('scheduling.booking.person_unavailable')]);
        }
    }
}

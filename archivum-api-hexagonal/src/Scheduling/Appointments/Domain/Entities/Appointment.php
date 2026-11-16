<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Domain\Entities;

use DateTimeImmutable;
use Src\Scheduling\Appointments\Domain\Exceptions\AppointmentChangeNotAllowedException;
use Src\Scheduling\Appointments\Domain\Exceptions\InvalidAppointmentTransitionException;
use Src\Scheduling\Appointments\Domain\Exceptions\TooManyPerfumesException;
use Src\Scheduling\Appointments\Domain\ValueObjects\AppointmentId;
use Src\Scheduling\Appointments\Domain\ValueObjects\AppointmentStatus;
use Src\Scheduling\Appointments\Domain\ValueObjects\CustomerId;
use Src\Scheduling\Appointments\Domain\ValueObjects\PerfumeId;
use Src\Scheduling\Appointments\Domain\ValueObjects\ServiceId;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\MemberId;

/**
 * A trial appointment of one business. The aggregate keeps the state machine of section 6 of the specification, the end
 * and the blocked range of the slot, the perfumes to try and the deadline of the customer to move or cancel it.
 *
 * Whether a slot is free is not decided here: the availability calculates it (#61) and the exclusion constraint of the
 * table guarantees it (#59).
 */
final class Appointment
{
    /**
     * Build the aggregate from its state; only the static factories use it, so every instance is valid.
     *
     * @param  list<PerfumeId>  $perfumes
     */
    private function __construct(
        public readonly AppointmentId $id,
        public readonly BusinessId $businessId,
        public readonly ServiceId $serviceId,
        public readonly CustomerId $customerId,
        public readonly MemberId $memberId,
        private DateTimeImmutable $startsAt,
        private DateTimeImmutable $endsAt,
        private DateTimeImmutable $blockedUntil,
        private array $perfumes,
        private AppointmentStatus $status,
        private ?DateTimeImmutable $holdExpiresAt,
        private ?DateTimeImmutable $cancelledAt,
        private ?string $cancelReason,
    ) {}

    /**
     * Book a new appointment: it lasts the duration of the service, keeps the margin blocked after it and starts confirmed.
     *
     * @param  list<PerfumeId>  $perfumes
     *
     * @throws TooManyPerfumesException
     */
    public static function book(
        AppointmentId $id,
        BusinessId $businessId,
        ServiceId $serviceId,
        CustomerId $customerId,
        MemberId $memberId,
        DateTimeImmutable $startsAt,
        int $durationMinutes,
        int $bufferMinutes,
        array $perfumes,
        int $maxPerfumes,
    ): self {
        self::assertPerfumes($perfumes, $maxPerfumes);

        $endsAt = $startsAt->modify("+{$durationMinutes} minutes");
        $blockedUntil = $endsAt->modify("+{$bufferMinutes} minutes");

        return new self(
            $id,
            $businessId,
            $serviceId,
            $customerId,
            $memberId,
            $startsAt,
            $endsAt,
            $blockedUntil,
            $perfumes,
            AppointmentStatus::Confirmed,
            null,
            null,
            null,
        );
    }

    /**
     * Rebuild a stored appointment without resetting its state.
     *
     * @param  list<PerfumeId>  $perfumes
     */
    public static function reconstitute(
        AppointmentId $id,
        BusinessId $businessId,
        ServiceId $serviceId,
        CustomerId $customerId,
        MemberId $memberId,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
        DateTimeImmutable $blockedUntil,
        array $perfumes,
        AppointmentStatus $status,
        ?DateTimeImmutable $holdExpiresAt = null,
        ?DateTimeImmutable $cancelledAt = null,
        ?string $cancelReason = null,
    ): self {
        if ($endsAt <= $startsAt) {
            throw new InvalidValueException('scheduling.appointment.invalid_order');
        }

        if ($blockedUntil < $endsAt) {
            throw new InvalidValueException('scheduling.appointment.invalid_block');
        }

        return new self(
            $id,
            $businessId,
            $serviceId,
            $customerId,
            $memberId,
            $startsAt,
            $endsAt,
            $blockedUntil,
            $perfumes,
            $status,
            $holdExpiresAt,
            $cancelledAt,
            $cancelReason,
        );
    }

    /** Start of the appointment, in the time zone of the store. */
    public function startsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    /** End of the appointment: the start plus the duration of the service. */
    public function endsAt(): DateTimeImmutable
    {
        return $this->endsAt;
    }

    /** End of the range the appointment blocks: its end plus the margin of the service. */
    public function blockedUntil(): DateTimeImmutable
    {
        return $this->blockedUntil;
    }

    /**
     * The perfumes to try, in the order they were chosen.
     *
     * @return list<PerfumeId>
     */
    public function perfumes(): array
    {
        return $this->perfumes;
    }

    /** Current state of the appointment. */
    public function status(): AppointmentStatus
    {
        return $this->status;
    }

    /** When an offer of the waiting list stops blocking the slot, while the appointment is held. */
    public function holdExpiresAt(): ?DateTimeImmutable
    {
        return $this->holdExpiresAt;
    }

    /** When the customer cancelled the appointment, once it is cancelled. */
    public function cancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    /** The reason the customer gave when cancelling, if any. */
    public function cancelReason(): ?string
    {
        return $this->cancelReason;
    }

    /** Turn a confirmed appointment into an offer of the waiting list, which blocks the slot until its deadline. */
    public function hold(DateTimeImmutable $expiresAt): void
    {
        $this->assertStatus(AppointmentStatus::Held, AppointmentStatus::Confirmed);
        $this->status = AppointmentStatus::Held;
        $this->holdExpiresAt = $expiresAt;
    }

    /** Confirm an offer of the waiting list. */
    public function confirm(): void
    {
        $this->assertStatus(AppointmentStatus::Confirmed, AppointmentStatus::Held);
        $this->status = AppointmentStatus::Confirmed;
        $this->holdExpiresAt = null;
    }

    /** Let an offer of the waiting list expire, so the slot is free again. */
    public function expire(): void
    {
        $this->assertStatus(AppointmentStatus::Expired, AppointmentStatus::Held);
        $this->status = AppointmentStatus::Expired;
        $this->holdExpiresAt = null;
    }

    /**
     * Cancel a confirmed appointment before the deadline and keep the date and the reason, so the history stays.
     *
     * @throws InvalidAppointmentTransitionException
     * @throws AppointmentChangeNotAllowedException
     */
    public function cancel(DateTimeImmutable $now, int $cutoffHours, ?string $reason = null): void
    {
        $this->assertStatus(AppointmentStatus::Cancelled, AppointmentStatus::Confirmed);
        $this->assertBeforeDeadline($now, $cutoffHours);

        $this->status = AppointmentStatus::Cancelled;
        $this->cancelledAt = $now;
        $this->cancelReason = $reason;
    }

    /** Mark a confirmed appointment as attended, which unblocks reviews and commissions. */
    public function complete(): void
    {
        $this->assertStatus(AppointmentStatus::Completed, AppointmentStatus::Confirmed);
        $this->status = AppointmentStatus::Completed;
    }

    /** Mark a confirmed appointment as not attended. */
    public function markNoShow(): void
    {
        $this->assertStatus(AppointmentStatus::NoShow, AppointmentStatus::Confirmed);
        $this->status = AppointmentStatus::NoShow;
    }

    /**
     * Move a confirmed appointment to another start before the deadline, keeping its person, its duration and its margin,
     * and recalculating the end and the blocked range from the new start.
     *
     * @throws InvalidAppointmentTransitionException
     * @throws AppointmentChangeNotAllowedException
     */
    public function reschedule(DateTimeImmutable $newStart, DateTimeImmutable $now, int $cutoffHours): void
    {
        $this->assertStatus(AppointmentStatus::Confirmed, AppointmentStatus::Confirmed);
        $this->assertBeforeDeadline($now, $cutoffHours);

        $duration = (int) round(($this->endsAt->getTimestamp() - $this->startsAt->getTimestamp()) / 60);
        $buffer = (int) round(($this->blockedUntil->getTimestamp() - $this->endsAt->getTimestamp()) / 60);

        $this->startsAt = $newStart;
        $this->endsAt = $newStart->modify("+{$duration} minutes");
        $this->blockedUntil = $this->endsAt->modify("+{$buffer} minutes");
    }

    /** Accept the transition only when the appointment is in one of the states that allow it. */
    private function assertStatus(AppointmentStatus $target, AppointmentStatus ...$allowed): void
    {
        if (! in_array($this->status, $allowed, true)) {
            throw InvalidAppointmentTransitionException::from($this->status, $target);
        }
    }

    /** The customer may only move or cancel the appointment up to the deadline the store gives. */
    private function assertBeforeDeadline(DateTimeImmutable $now, int $cutoffHours): void
    {
        if ($now > $this->startsAt->modify("-{$cutoffHours} hours")) {
            throw AppointmentChangeNotAllowedException::forDeadline($cutoffHours);
        }
    }

    /**
     * Refuse more perfumes than the service allows and a perfume chosen twice.
     *
     * @param  list<PerfumeId>  $perfumes
     *
     * @throws TooManyPerfumesException
     */
    private static function assertPerfumes(array $perfumes, int $maxPerfumes): void
    {
        if (count($perfumes) > $maxPerfumes) {
            throw TooManyPerfumesException::forLimit($maxPerfumes);
        }

        $values = array_map(fn (PerfumeId $perfume): string => $perfume->value, $perfumes);

        if (count(array_unique($values)) !== count($values)) {
            throw new InvalidValueException('scheduling.appointment.repeated_perfume');
        }
    }
}

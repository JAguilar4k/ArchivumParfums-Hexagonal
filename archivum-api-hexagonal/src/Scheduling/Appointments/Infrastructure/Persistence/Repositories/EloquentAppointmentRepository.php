<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDOException;
use Src\Scheduling\Appointments\Domain\Contracts\AppointmentRepository;
use Src\Scheduling\Appointments\Domain\Entities\Appointment;
use Src\Scheduling\Appointments\Domain\Exceptions\AppointmentSlotTakenException;
use Src\Scheduling\Appointments\Domain\ValueObjects\AppointmentId;
use Src\Scheduling\Appointments\Domain\ValueObjects\AppointmentStatus;
use Src\Scheduling\Appointments\Domain\ValueObjects\CustomerId;
use Src\Scheduling\Appointments\Domain\ValueObjects\PerfumeId;
use Src\Scheduling\Appointments\Domain\ValueObjects\ServiceId;
use Src\Scheduling\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use Src\Scheduling\Appointments\Infrastructure\Persistence\Models\AppointmentPerfumeModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\MemberId;
use Throwable;

/**
 * Data mapper between the appointment aggregate and its two tables. Every read and every write is pinned to the
 * business of the context, so the row-level policies are the second barrier and never the only one.
 */
final readonly class EloquentAppointmentRepository implements AppointmentRepository
{
    /** SQLSTATE PostgreSQL answers when the exclusion constraint of the slot rejects the row. */
    private const string EXCLUSION_VIOLATION = '23P01';

    /** SQLSTATE PostgreSQL answers when it breaks a deadlock, such as two inserts of the same slot waiting on each other. */
    private const string DEADLOCK_DETECTED = '40P01';

    /** Receive the business of the request the appointment belongs to. */
    public function __construct(private BusinessContext $business) {}

    public function nextId(): AppointmentId
    {
        return new AppointmentId((string) Str::uuid7());
    }

    public function find(AppointmentId $id): ?Appointment
    {
        $model = $this->query()->where('appointments.id', $id->value)->first();

        return $model === null ? null : $this->toDomain($model);
    }

    /**
     * A PostgreSQL error aborts the transaction it runs in, so the exclusion failure is translated once the
     * transaction has been rolled back and the connection is usable again. Two bookings of the same slot can also wait
     * on each other while the constraint decides which one keeps it, and PostgreSQL breaks that deadlock by failing one
     * of them: that one runs its transaction once more, when the other has already taken the slot or let it go, so the
     * second try answers the conflict or stores the appointment. A second deadlock stays as it is, like any other error.
     */
    public function save(Appointment $appointment): void
    {
        if ($appointment->businessId->value !== $this->business->current()->value) {
            throw new InvalidValueException('scheduling.appointment.invalid_business');
        }

        try {
            $this->write($appointment);
        } catch (QueryException|DeadlockException $exception) {
            if (! $this->hasState($exception, self::DEADLOCK_DETECTED)) {
                throw $this->translated($exception);
            }

            try {
                $this->write($appointment);
            } catch (QueryException|DeadlockException $again) {
                throw $this->translated($again);
            }
        }
    }

    /**
     * Write the appointment and its perfumes in their own transaction. Inside the transaction of the request it is a
     * savepoint, and Laravel answers a deadlock there with a DeadlockException once it has rolled the savepoint back.
     */
    private function write(Appointment $appointment): void
    {
        DB::transaction(function () use ($appointment): void {
            $this->writeAppointment($appointment);
            $this->writePerfumes($appointment);
        });
    }

    /** The conflict of the domain when the exclusion constraint refused the slot; any other error stays as it is. */
    private function translated(PDOException $exception): PDOException|AppointmentSlotTakenException
    {
        return $this->hasState($exception, self::EXCLUSION_VIOLATION) ? AppointmentSlotTakenException::create() : $exception;
    }

    /** Store the row of the appointment, inserting it the first time and updating it afterwards. */
    private function writeAppointment(Appointment $appointment): void
    {
        AppointmentModel::query()->updateOrCreate(
            ['id' => $appointment->id->value, 'business_id' => $appointment->businessId->value],
            [
                'service_id' => $appointment->serviceId->value,
                'customer_id' => $appointment->customerId->value,
                'member_id' => $appointment->memberId->value,
                'starts_at' => $this->stored($appointment->startsAt()),
                'ends_at' => $this->stored($appointment->endsAt()),
                'blocked_until' => $this->stored($appointment->blockedUntil()),
                'status' => $appointment->status()->value,
                'hold_expires_at' => $this->stored($appointment->holdExpiresAt()),
                'cancelled_at' => $this->stored($appointment->cancelledAt()),
                'cancel_reason' => $appointment->cancelReason(),
            ],
        );
    }

    /**
     * The instant in the time zone of the connection. Eloquent writes a date as its wall time without the offset and
     * PostgreSQL reads that wall time in the zone of the connection, so an instant with another offset, such as the one
     * the customer sent, would be stored hours away from itself.
     */
    private function stored(?DateTimeImmutable $instant): ?DateTimeImmutable
    {
        return $instant?->setTimezone(new DateTimeZone((string) config('database.connections.pgsql.timezone', 'UTC')));
    }

    /** Keep one row per perfume of the appointment and drop the ones it no longer tries. */
    private function writePerfumes(Appointment $appointment): void
    {
        $businessId = $appointment->businessId->value;
        $perfumeIds = array_map(fn (PerfumeId $perfume): string => $perfume->value, $appointment->perfumes());

        AppointmentPerfumeModel::query()
            ->where('business_id', $businessId)
            ->where('appointment_id', $appointment->id->value)
            ->whereNotIn('perfume_id', $perfumeIds)
            ->delete();

        foreach ($perfumeIds as $perfumeId) {
            AppointmentPerfumeModel::query()->updateOrCreate(
                ['business_id' => $businessId, 'appointment_id' => $appointment->id->value, 'perfume_id' => $perfumeId],
                [],
            );
        }
    }

    /** @return Builder<AppointmentModel> */
    private function query(): Builder
    {
        $businessId = $this->business->current()->value;

        return AppointmentModel::query()
            ->with(['perfumes' => fn ($query) => $query->where('appointment_perfumes.business_id', $businessId)])
            ->where('appointments.business_id', $businessId);
    }

    /** Rebuild the aggregate from its row and the perfumes the appointment tries. */
    private function toDomain(AppointmentModel $model): Appointment
    {
        /** @var Collection<int, AppointmentPerfumeModel> $perfumes */
        $perfumes = $model->perfumes;

        return Appointment::reconstitute(
            new AppointmentId($model->id),
            new BusinessId($model->business_id),
            new ServiceId($model->service_id),
            new CustomerId($model->customer_id),
            new MemberId($model->member_id),
            DateTimeImmutable::createFromInterface($model->starts_at),
            DateTimeImmutable::createFromInterface($model->ends_at),
            DateTimeImmutable::createFromInterface($model->blocked_until),
            array_values($perfumes->map(fn (AppointmentPerfumeModel $perfume): PerfumeId => new PerfumeId($perfume->perfume_id))->all()),
            AppointmentStatus::from($model->status),
            $model->hold_expires_at === null ? null : DateTimeImmutable::createFromInterface($model->hold_expires_at),
            $model->cancelled_at === null ? null : DateTimeImmutable::createFromInterface($model->cancelled_at),
            $model->cancel_reason,
        );
    }

    /**
     * Whether PostgreSQL answered the SQLSTATE. Laravel wraps a deadlock in one more DeadlockException at every savepoint
     * it rolls back, such as the one updateOrCreate() opens for its insert, so the state is looked up along the chain.
     */
    private function hasState(Throwable $exception, string $state): bool
    {
        for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause->getCode() === $state) {
                return true;
            }
        }

        return false;
    }
}

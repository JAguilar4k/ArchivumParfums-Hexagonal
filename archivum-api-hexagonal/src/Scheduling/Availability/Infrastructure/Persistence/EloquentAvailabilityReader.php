<?php

declare(strict_types=1);

namespace Src\Scheduling\Availability\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSettings;
use Src\Scheduling\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use Src\Scheduling\Availability\Application\Contracts\AvailabilityReader;
use Src\Scheduling\Availability\Application\DTOs\AvailableService;
use Src\Scheduling\Availability\Application\DTOs\BusyRange;
use Src\Scheduling\Availability\Application\DTOs\PersonAvailability;
use Src\Scheduling\Availability\Application\DTOs\StoreAvailability;
use Src\Scheduling\BusinessHours\Infrastructure\Persistence\Models\BusinessHoursModel;
use Src\Scheduling\ScheduleBlocks\Infrastructure\Persistence\Models\ScheduleBlockModel;
use Src\Scheduling\Services\Domain\ValueObjects\ServiceId;
use Src\Scheduling\Services\Infrastructure\Persistence\Models\ServiceModel;
use Src\Scheduling\Shared\Domain\ValueObjects\TimeRange;
use Src\Scheduling\Shared\Domain\ValueObjects\Weekday;
use Src\Scheduling\StaffSchedules\Infrastructure\Persistence\Models\StaffScheduleModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\MemberId;

/**
 * Reader of the pieces the availability of a day needs. It joins the tables of the scheduling schema in a few queries
 * and filters every one by the business pinned in the transaction, so a row of another store is never read.
 */
final readonly class EloquentAvailabilityReader implements AvailabilityReader
{
    /** Statuses whose appointment keeps the slot blocked, as the exclusion constraint of the table understands it. */
    private const array OCCUPYING_STATUSES = ['held', 'confirmed'];

    /** Receive the business of the request and the repository that rebuilds the settings of the store. */
    public function __construct(
        private BusinessContext $context,
        private BusinessRepository $businesses,
    ) {}

    public function store(): StoreAvailability
    {
        $business = $this->businesses->find($this->context->current());

        return new StoreAvailability(
            $business?->timezone->value ?? (string) config('app.timezone'),
            $business?->settings->minimumNoticeHours ?? BusinessSettings::DEFAULT_MINIMUM_NOTICE_HOURS,
            $business?->settings->bookingHorizonDays ?? BusinessSettings::DEFAULT_BOOKING_HORIZON_DAYS,
        );
    }

    public function service(ServiceId $id): ?AvailableService
    {
        $model = ServiceModel::query()
            ->where('business_id', $this->context->current()->value)
            ->whereKey($id->value)
            ->where('is_active', true)
            ->first();

        return $model === null ? null : new AvailableService((int) $model->duration_minutes, (int) $model->buffer_minutes);
    }

    /** @return list<TimeRange> */
    public function businessHours(Weekday $weekday): array
    {
        $rows = BusinessHoursModel::query()
            ->where('business_id', $this->context->current()->value)
            ->where('weekday', $weekday->value)
            ->orderBy('opens_at')
            ->get();

        return array_values($rows->map(fn (BusinessHoursModel $row): TimeRange => TimeRange::create(
            substr((string) $row->opens_at, 0, 5),
            substr((string) $row->closes_at, 0, 5),
        ))->all());
    }

    /** @return list<PersonAvailability> */
    public function people(Weekday $weekday, DateTimeImmutable $dayStart, DateTimeImmutable $dayEnd, ?MemberId $only): array
    {
        $onlyId = $only?->value;

        $memberRows = MemberModel::query()
            ->join('users', 'users.id', '=', 'members.user_id')
            ->where('members.business_id', $this->context->current()->value)
            ->where('members.attends_appointments', true)
            ->when($onlyId !== null, fn ($query) => $query->where('members.id', $onlyId))
            ->orderBy('users.name')
            ->select(['members.id as id', 'users.name as name'])
            ->get();

        if ($memberRows->isEmpty()) {
            return [];
        }

        $memberIds = array_values(array_map(fn (MemberModel $member): string => (string) $member->getAttribute('id'), $memberRows->all()));

        $schedules = StaffScheduleModel::query()
            ->where('business_id', $this->context->current()->value)
            ->whereIn('member_id', $memberIds)
            ->where('weekday', $weekday->value)
            ->orderBy('starts_at')
            ->get()
            ->groupBy('member_id');

        $blocks = $this->blocks($dayStart, $dayEnd, $memberIds);
        $appointments = $this->appointments($dayStart, $dayEnd, $memberIds);

        $people = [];

        foreach ($memberRows as $member) {
            $memberId = (string) $member->getAttribute('id');
            $workingHours = $schedules->get($memberId);

            $people[] = new PersonAvailability(
                $memberId,
                (string) $member->getAttribute('name'),
                $this->minutesOfTheDay($workingHours instanceof Collection ? $workingHours : null, 'starts_at', 'ends_at'),
                $this->busyRanges(array_values($blocks->filter(
                    fn (ScheduleBlockModel $block): bool => $block->member_id === null || $block->member_id === $memberId,
                )->all())),
                $this->busyRanges(array_values($appointments->filter(
                    fn (AppointmentModel $appointment): bool => $appointment->member_id === $memberId,
                )->all())),
            );
        }

        return $people;
    }

    /**
     * The tramos of one person that weekday, read as the times of the day the columns store.
     *
     * @param  Collection<array-key, StaffScheduleModel>|null  $rows
     * @return list<TimeRange>
     */
    private function minutesOfTheDay(?Collection $rows, string $from, string $to): array
    {
        return array_values(($rows ?? new Collection)->map(fn (StaffScheduleModel $row): TimeRange => TimeRange::create(
            substr((string) $row->getAttribute($from), 0, 5),
            substr((string) $row->getAttribute($to), 0, 5),
        ))->all());
    }

    /**
     * The blocks of the people and the ones of the whole store that cross the day.
     *
     * @param  list<string>  $memberIds
     * @return Collection<array-key, ScheduleBlockModel>
     */
    private function blocks(DateTimeImmutable $dayStart, DateTimeImmutable $dayEnd, array $memberIds): Collection
    {
        return ScheduleBlockModel::query()
            ->where('business_id', $this->context->current()->value)
            ->where('starts_at', '<', $this->instant($dayEnd))
            ->where('ends_at', '>', $this->instant($dayStart))
            ->where(fn ($query) => $query->whereNull('member_id')->orWhereIn('member_id', $memberIds))
            ->get();
    }

    /**
     * The appointments that still keep the slot of one of those people, as the exclusion constraint reads them.
     *
     * @param  list<string>  $memberIds
     * @return Collection<array-key, AppointmentModel>
     */
    private function appointments(DateTimeImmutable $dayStart, DateTimeImmutable $dayEnd, array $memberIds): Collection
    {
        return AppointmentModel::query()
            ->where('business_id', $this->context->current()->value)
            ->whereIn('member_id', $memberIds)
            ->whereIn('status', self::OCCUPYING_STATUSES)
            ->where('starts_at', '<', $this->instant($dayEnd))
            ->where('blocked_until', '>', $this->instant($dayStart))
            ->get();
    }

    /**
     * @param  list<ScheduleBlockModel|AppointmentModel>  $rows
     * @return list<BusyRange>
     */
    private function busyRanges(array $rows): array
    {
        return array_map(fn (ScheduleBlockModel|AppointmentModel $row): BusyRange => new BusyRange(
            $this->instantOf($row, 'starts_at'),
            $this->instantOf($row, $row instanceof AppointmentModel ? 'blocked_until' : 'ends_at'),
        ), $rows);
    }

    /** The instant an attribute holds, whether the model cast it to a date or left the text of the column. */
    private function instantOf(Model $row, string $attribute): DateTimeImmutable
    {
        $value = $row->getAttribute($attribute);

        return $value instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)
            : new DateTimeImmutable((string) $value);
    }

    /** The instant as the connection reads it, so a store in another time zone still compares the right way. */
    private function instant(DateTimeImmutable $instant): string
    {
        return $instant
            ->setTimezone(new DateTimeZone((string) config('database.connections.pgsql.timezone', 'UTC')))
            ->format('Y-m-d H:i:s');
    }
}

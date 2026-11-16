<?php

declare(strict_types=1);

namespace Src\Scheduling\StaffSchedules\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Scheduling\Shared\Domain\ValueObjects\TimeOfDay;
use Src\Scheduling\Shared\Domain\ValueObjects\TimeRange;
use Src\Scheduling\StaffSchedules\Domain\Contracts\StaffScheduleRepository;
use Src\Scheduling\StaffSchedules\Domain\Entities\StaffSchedule;
use Src\Scheduling\StaffSchedules\Domain\ValueObjects\StaffScheduleId;
use Src\Scheduling\StaffSchedules\Infrastructure\Persistence\Models\StaffScheduleModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\MemberId;

final readonly class EloquentStaffScheduleRepository implements StaffScheduleRepository
{
    /** Take the business that every query of the repository filters by. */
    public function __construct(private BusinessContext $context) {}

    /** Generate a time-ordered UUID for a new schedule. */
    public function nextId(): StaffScheduleId
    {
        return new StaffScheduleId((string) Str::uuid7());
    }

    /**
     * Delete the tramos of that person in the business of the context and write the new ones in the same transaction, so
     * a refused write leaves the previous schedule as it was.
     */
    public function replace(StaffSchedule $staffSchedule): void
    {
        $business = $this->context->current();
        $member = $staffSchedule->memberId->value;

        DB::transaction(function () use ($staffSchedule, $business, $member): void {
            StaffScheduleModel::query()
                ->where('business_id', $business->value)
                ->where('member_id', $member)
                ->delete();

            foreach ($staffSchedule->getSlots() as $weekday => $ranges) {
                foreach ($ranges as $range) {
                    StaffScheduleModel::query()->create([
                        'id' => (string) Str::uuid7(),
                        'business_id' => $business->value,
                        'member_id' => $member,
                        'weekday' => $weekday,
                        'starts_at' => $this->asClock($range->start),
                        'ends_at' => $this->asClock($range->end),
                    ]);
                }
            }
        });
    }

    /** Rebuild the aggregate of that person ordered by weekday and starting hour; the hours travel as HH:MM. */
    public function ofMember(MemberId $member): StaffSchedule
    {
        $business = $this->context->current();

        $rows = StaffScheduleModel::query()
            ->where('business_id', $business->value)
            ->where('member_id', $member->value)
            ->orderBy('weekday')
            ->orderBy('starts_at')
            ->get();

        $slots = [];

        foreach ($rows as $row) {
            $slots[(int) $row->weekday][] = TimeRange::create(
                substr((string) $row->starts_at, 0, 5),
                substr((string) $row->ends_at, 0, 5),
            );
        }

        return StaffSchedule::reconstitute($this->nextId(), $business, $member, $slots);
    }

    /** Whether that person attends appointments, or null when she is not staff of this business. */
    public function memberAttendsAppointments(MemberId $member): ?bool
    {
        $row = MemberModel::query()
            ->where('business_id', $this->context->current()->value)
            ->where('id', $member->value)
            ->first(['attends_appointments']);

        return $row === null ? null : (bool) $row->attends_appointments;
    }

    /** Write a time of day as the HH:MM text the column stores; the value object keeps only its minutes. */
    private function asClock(TimeOfDay $time): string
    {
        return sprintf('%02d:%02d', intdiv($time->minutes, 60), $time->minutes % 60);
    }
}

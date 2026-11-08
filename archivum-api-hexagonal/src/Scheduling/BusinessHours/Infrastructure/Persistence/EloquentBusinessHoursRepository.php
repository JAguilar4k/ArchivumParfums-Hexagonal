<?php

declare(strict_types=1);

namespace Src\Scheduling\BusinessHours\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Scheduling\BusinessHours\Domain\Contracts\BusinessHoursRepository;
use Src\Scheduling\BusinessHours\Domain\Entities\BusinessHours;
use Src\Scheduling\BusinessHours\Domain\ValueObjects\BusinessHoursId;
use Src\Scheduling\BusinessHours\Infrastructure\Persistence\Models\BusinessHoursModel;
use Src\Scheduling\Shared\Domain\ValueObjects\TimeRange;
use Src\Shared\Domain\Contracts\BusinessContext;

final readonly class EloquentBusinessHoursRepository implements BusinessHoursRepository
{
    /** Take the business that every query of the repository filters by. */
    public function __construct(private BusinessContext $context) {}

    /** Generate a time-ordered UUID for a new schedule. */
    public function nextId(): BusinessHoursId
    {
        return new BusinessHoursId((string) Str::uuid7());
    }

    /**
     * Delete the tramos of the business of the context and write the new ones in the same transaction, so a refused
     * write leaves the previous schedule as it was.
     */
    public function replace(BusinessHours $businessHours): void
    {
        $business = $this->context->current();

        DB::transaction(function () use ($businessHours, $business): void {
            BusinessHoursModel::query()->where('business_id', $business->value)->delete();

            foreach ($businessHours->getSlots() as $weekday => $ranges) {
                foreach ($ranges as $range) {
                    BusinessHoursModel::query()->create([
                        'id' => (string) Str::uuid7(),
                        'business_id' => $business->value,
                        'weekday' => $weekday,
                        'opens_at' => $range->start,
                        'closes_at' => $range->end,
                    ]);
                }
            }
        });
    }

    /** Rebuild the aggregate ordered by weekday and opening hour; the hours travel as HH:MM, as the contract says. */
    public function all(): BusinessHours
    {
        $business = $this->context->current();

        $rows = BusinessHoursModel::query()
            ->where('business_id', $business->value)
            ->orderBy('weekday')
            ->orderBy('opens_at')
            ->get();

        $slots = [];

        foreach ($rows as $row) {
            $slots[(int) $row->weekday][] = TimeRange::create(
                substr((string) $row->opens_at, 0, 5),
                substr((string) $row->closes_at, 0, 5),
            );
        }

        return BusinessHours::reconstitute($this->nextId(), $business, $slots);
    }
}

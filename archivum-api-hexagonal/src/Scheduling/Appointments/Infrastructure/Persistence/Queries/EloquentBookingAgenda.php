<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Persistence\Queries;

use DateTimeImmutable;
use DateTimeZone;
use Src\Scheduling\Appointments\Application\Contracts\BookingAgenda;
use Src\Scheduling\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use Src\Shared\Domain\Contracts\BusinessContext;

/** Counts the active appointments of each person of the store, which is the balance R15 keeps. */
final readonly class EloquentBookingAgenda implements BookingAgenda
{
    /** Statuses whose appointment counts as taken, as the exclusion constraint reads them. */
    private const array OCCUPYING_STATUSES = ['held', 'confirmed'];

    /** Take the business every count is limited to. */
    public function __construct(private BusinessContext $business) {}

    public function countsFor(DateTimeImmutable $from, DateTimeImmutable $to, array $memberIds): array
    {
        if ($memberIds === []) {
            return [];
        }

        $rows = AppointmentModel::query()
            ->where('business_id', $this->business->current()->value)
            ->whereIn('member_id', $memberIds)
            ->whereIn('status', self::OCCUPYING_STATUSES)
            ->where('starts_at', '>=', $this->instant($from))
            ->where('starts_at', '<', $this->instant($to))
            ->groupBy('member_id')
            ->selectRaw('member_id, count(*) as total')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row->getAttribute('member_id')] = (int) $row->getAttribute('total');
        }

        return $counts;
    }

    /** The instant as the connection reads it, so a store in another time zone still compares the right way. */
    private function instant(DateTimeImmutable $instant): string
    {
        return $instant
            ->setTimezone(new DateTimeZone((string) config('database.connections.pgsql.timezone', 'UTC')))
            ->format('Y-m-d H:i:s');
    }
}

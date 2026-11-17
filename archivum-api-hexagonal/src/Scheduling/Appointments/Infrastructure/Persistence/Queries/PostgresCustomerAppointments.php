<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Persistence\Queries;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeModel;
use Src\Scheduling\Appointments\Application\Contracts\CustomerAppointments;
use Src\Scheduling\Appointments\Application\DTOs\AppointmentView;
use Src\Scheduling\Appointments\Domain\ValueObjects\AppointmentPeriod;
use Src\Scheduling\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use Src\Shared\Domain\Contracts\BusinessContext;

/**
 * Reads the appointments of one customer with their store, service, person and perfumes in a few queries, so the
 * listing never walks the aggregate nor falls into N+1 (#141). Every query is limited to the business of the context.
 */
final readonly class PostgresCustomerAppointments implements CustomerAppointments
{
    /** Receive the business the queries are limited to. */
    public function __construct(private BusinessContext $business) {}

    /** @return list<AppointmentView> */
    public function of(string $userId, AppointmentPeriod $period): array
    {
        $query = $this->query()->where('customers.user_id', $userId);
        $now = $this->instant(new DateTimeImmutable('now'));

        if ($period === AppointmentPeriod::Upcoming) {
            $query->where('appointments.starts_at', '>=', $now)
                ->orderBy('appointments.starts_at')
                ->orderBy('appointments.id');
        } else {
            $query->where('appointments.starts_at', '<', $now)
                ->orderByDesc('appointments.starts_at')
                ->orderByDesc('appointments.id');
        }

        return $this->views(array_values($query->get()->all()));
    }

    public function find(string $id, string $userId): ?AppointmentView
    {
        $rows = array_values($this->query()->where('customers.user_id', $userId)->where('appointments.id', $id)->get()->all());

        return $rows === [] ? null : $this->views($rows)[0];
    }

    public function viewOf(string $id): ?AppointmentView
    {
        $rows = array_values($this->query()->where('appointments.id', $id)->get()->all());

        return $rows === [] ? null : $this->views($rows)[0];
    }

    /** @return Builder<AppointmentModel> */
    private function query(): Builder
    {
        $businessId = $this->business->current()->value;

        return AppointmentModel::query()
            ->with(['perfumes' => fn ($query) => $query->where('appointment_perfumes.business_id', $businessId)])
            ->join('customers', 'customers.id', '=', 'appointments.customer_id')
            ->join('businesses', 'businesses.id', '=', 'appointments.business_id')
            ->join('services', 'services.id', '=', 'appointments.service_id')
            ->join('members', 'members.id', '=', 'appointments.member_id')
            ->join('users', 'users.id', '=', 'members.user_id')
            ->whereColumn('customers.business_id', 'appointments.business_id')
            ->whereColumn('services.business_id', 'appointments.business_id')
            ->whereColumn('members.business_id', 'appointments.business_id')
            ->where('appointments.business_id', $businessId)
            ->select([
                'appointments.*',
                'businesses.name as business_name',
                'businesses.location_text as business_place',
                'services.name as service_name',
                'users.name as member_name',
            ]);
    }

    /**
     * Turn the rows into the views of the customer, with the names of the perfumes read in one more query.
     *
     * @param  list<AppointmentModel>  $rows
     * @return list<AppointmentView>
     */
    private function views(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $perfumeIds = [];

        foreach ($rows as $row) {
            foreach ($row->perfumes as $perfume) {
                $perfumeIds[] = (string) $perfume->getAttribute('perfume_id');
            }
        }

        $names = $this->names($perfumeIds);

        return array_map(function (AppointmentModel $row) use ($names): AppointmentView {
            $perfumes = [];

            foreach ($row->perfumes as $perfume) {
                $id = (string) $perfume->getAttribute('perfume_id');
                $perfumes[] = ['id' => $id, 'name' => $names[$id] ?? ''];
            }

            return new AppointmentView(
                (string) $row->getAttribute('id'),
                (string) $row->getAttribute('status'),
                (string) $row->getAttribute('business_id'),
                (string) $row->getAttribute('business_name'),
                (string) $row->getAttribute('service_id'),
                (string) $row->getAttribute('service_name'),
                (string) $row->getAttribute('member_id'),
                (string) $row->getAttribute('member_name'),
                $this->dateOf($row->getAttribute('starts_at')),
                $this->dateOf($row->getAttribute('ends_at')),
                $row->getAttribute('business_place') === null ? null : (string) $row->getAttribute('business_place'),
                $perfumes,
            );
        }, $rows);
    }

    /**
     * The names of the perfumes of those appointments, keyed by identity.
     *
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    private function names(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $names = [];

        $rows = PerfumeModel::query()
            ->where('business_id', $this->business->current()->value)
            ->whereIn('id', array_values(array_unique($ids)))
            ->get();

        foreach ($rows as $row) {
            $names[(string) $row->id] = (string) $row->name;
        }

        return $names;
    }

    /** The instant of a column the query read, whether the model cast it or left it as text. */
    private function dateOf(mixed $value): DateTimeImmutable
    {
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

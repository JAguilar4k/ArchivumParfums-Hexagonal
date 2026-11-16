<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Persistence\Queries;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeModel;
use Src\Scheduling\Appointments\Application\Contracts\StaffAgenda;
use Src\Scheduling\Appointments\Application\DTOs\StaffAgendaFilter;
use Src\Scheduling\Appointments\Application\DTOs\StaffAppointmentView;
use Src\Scheduling\Appointments\Infrastructure\Persistence\Models\AppointmentModel;
use Src\Shared\Domain\Contracts\BusinessContext;

/**
 * Reads the agenda of the staff of the store of the context with the customer, the service, the person and the perfumes
 * in a fixed number of queries, so the listing never walks the aggregate nor falls into N+1 (#141). Every query is
 * limited to the business of the context.
 */
final readonly class PostgresStaffAgenda implements StaffAgenda
{
    /** Receive the business the queries are limited to. */
    public function __construct(private BusinessContext $business) {}

    /** @return list<StaffAppointmentView> */
    public function of(StaffAgendaFilter $filter): array
    {
        $query = $this->query()
            ->where('appointments.starts_at', '>=', $this->instant($filter->from))
            ->where('appointments.starts_at', '<', $this->instant($filter->to))
            ->when($filter->memberId !== null, fn (Builder $query): Builder => $query->where('appointments.member_id', $filter->memberId))
            ->when($filter->status !== null, fn (Builder $query): Builder => $query->where('appointments.status', $filter->status))
            ->orderBy('appointments.starts_at')
            ->orderBy('appointments.id');

        return $this->views(array_values($query->get()->all()));
    }

    public function viewOf(string $id): ?StaffAppointmentView
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
            ->join('users as customer_users', 'customer_users.id', '=', 'customers.user_id')
            ->join('services', 'services.id', '=', 'appointments.service_id')
            ->join('members', 'members.id', '=', 'appointments.member_id')
            ->join('users as member_users', 'member_users.id', '=', 'members.user_id')
            ->whereColumn('customers.business_id', 'appointments.business_id')
            ->whereColumn('services.business_id', 'appointments.business_id')
            ->whereColumn('members.business_id', 'appointments.business_id')
            ->where('appointments.business_id', $businessId)
            ->select([
                'appointments.*',
                'customer_users.name as customer_name',
                'services.name as service_name',
                'member_users.name as member_name',
            ]);
    }

    /**
     * Turn the rows into the views of the staff, with the brand and the name of the perfumes read in one more query.
     *
     * @param  list<AppointmentModel>  $rows
     * @return list<StaffAppointmentView>
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

        $perfumes = $this->perfumes($perfumeIds);

        return array_map(function (AppointmentModel $row) use ($perfumes): StaffAppointmentView {
            $toTry = [];

            foreach ($row->perfumes as $perfume) {
                $id = (string) $perfume->getAttribute('perfume_id');
                $toTry[] = ['id' => $id, 'brand' => $perfumes[$id]['brand'] ?? '', 'name' => $perfumes[$id]['name'] ?? ''];
            }

            return new StaffAppointmentView(
                (string) $row->getAttribute('id'),
                (string) $row->getAttribute('status'),
                (string) $row->getAttribute('customer_id'),
                (string) $row->getAttribute('customer_name'),
                (string) $row->getAttribute('service_id'),
                (string) $row->getAttribute('service_name'),
                (string) $row->getAttribute('member_id'),
                (string) $row->getAttribute('member_name'),
                $this->dateOf($row->getAttribute('starts_at')),
                $this->dateOf($row->getAttribute('ends_at')),
                $toTry,
            );
        }, $rows);
    }

    /**
     * The brand and the name of the perfumes of those appointments, keyed by identity.
     *
     * @param  list<string>  $ids
     * @return array<string, array{brand: string, name: string}>
     */
    private function perfumes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $perfumes = [];

        $rows = PerfumeModel::query()
            ->where('business_id', $this->business->current()->value)
            ->whereIn('id', array_values(array_unique($ids)))
            ->get();

        foreach ($rows as $row) {
            $perfumes[(string) $row->id] = ['brand' => (string) $row->brand, 'name' => (string) $row->name];
        }

        return $perfumes;
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

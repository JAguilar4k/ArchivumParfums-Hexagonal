<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Catalog;

use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSettings;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeModel;
use Src\Scheduling\Appointments\Application\Contracts\BookingCatalog;
use Src\Scheduling\Appointments\Application\DTOs\BookingTerms;
use Src\Scheduling\Appointments\Application\DTOs\PersonOption;
use Src\Scheduling\Appointments\Application\DTOs\ServiceOption;
use Src\Scheduling\Appointments\Domain\ValueObjects\ServiceId;
use Src\Scheduling\Services\Infrastructure\Persistence\Models\ServiceModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\MemberId;

/** Adapter of the store catalogue that booking reads: its terms, its services, its people and its perfumes. */
final readonly class CatalogBookingCatalog implements BookingCatalog
{
    /** Receive the business of the request and the repository that rebuilds its settings. */
    public function __construct(
        private BusinessContext $business,
        private BusinessRepository $businesses,
    ) {}

    public function terms(): BookingTerms
    {
        $store = $this->businesses->find($this->business->current());

        return new BookingTerms(
            $store?->timezone->value ?? (string) config('app.timezone'),
            $store?->settings->minimumNoticeHours ?? BusinessSettings::DEFAULT_MINIMUM_NOTICE_HOURS,
            $store?->settings->bookingHorizonDays ?? BusinessSettings::DEFAULT_BOOKING_HORIZON_DAYS,
            $store?->settings->appointmentChangeCutoffHours ?? BusinessSettings::DEFAULT_APPOINTMENT_CHANGE_CUTOFF_HOURS,
        );
    }

    public function service(ServiceId $id): ?ServiceOption
    {
        $model = ServiceModel::query()
            ->where('business_id', $this->business->current()->value)
            ->whereKey($id->value)
            ->where('is_active', true)
            ->first();

        return $model === null ? null : new ServiceOption(
            (string) $model->id,
            (string) $model->name,
            (int) $model->duration_minutes,
            (int) $model->buffer_minutes,
            (int) $model->max_perfumes,
        );
    }

    public function person(MemberId $id): ?PersonOption
    {
        $row = MemberModel::query()
            ->join('users', 'users.id', '=', 'members.user_id')
            ->where('members.business_id', $this->business->current()->value)
            ->where('members.id', $id->value)
            ->where('members.attends_appointments', true)
            ->select(['members.id as id', 'users.name as name'])
            ->first();

        return $row === null ? null : new PersonOption(
            (string) $row->getAttribute('id'),
            (string) $row->getAttribute('name'),
        );
    }

    public function perfumes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $perfumes = [];

        $rows = PerfumeModel::query()
            ->where('business_id', $this->business->current()->value)
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get();

        foreach ($rows as $row) {
            $perfumes[(string) $row->id] = (string) $row->name;
        }

        return $perfumes;
    }

    public function appointmentPlace(): ?string
    {
        return $this->businesses->find($this->business->current())?->appointmentPlace?->value;
    }
}

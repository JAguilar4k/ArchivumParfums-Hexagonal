<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Infrastructure\Persistence;

use Illuminate\Support\Str;
use Src\Scheduling\Services\Domain\Contracts\ServiceRepository;
use Src\Scheduling\Services\Domain\Entities\Service;
use Src\Scheduling\Services\Domain\Exceptions\ServiceNotFoundException;
use Src\Scheduling\Services\Domain\ValueObjects\ServiceId;
use Src\Scheduling\Services\Infrastructure\Persistence\Models\ServiceModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;

final readonly class EloquentServiceRepository implements ServiceRepository
{
    /** Take the business that every query of the repository filters by. */
    public function __construct(private BusinessContext $context) {}

    /** Generate a time-ordered UUID for a new service. */
    public function nextId(): ServiceId
    {
        return new ServiceId((string) Str::uuid7());
    }

    /**
     * Write the service in the business of the context. A service of another business is refused before anything is
     * written: this check is the first barrier and the row-level policy of PostgreSQL the second.
     *
     * @throws ServiceNotFoundException
     */
    public function save(Service $service): void
    {
        $business = $this->context->current();

        if (! $service->businessId->equals($business)) {
            throw ServiceNotFoundException::create();
        }

        ServiceModel::query()->updateOrCreate(
            ['id' => $service->id->value, 'business_id' => $business->value],
            [
                'name' => $service->name,
                'duration_minutes' => $service->durationMinutes,
                'price' => $service->price->amount,
                'buffer_minutes' => $service->bufferMinutes,
                'max_perfumes' => $service->maxPerfumes,
                'is_active' => $service->isActive(),
            ],
        );
    }

    /** Find a service of the business of the context; one of another business is never reached. */
    public function findById(ServiceId $id): ?Service
    {
        $model = ServiceModel::query()
            ->where('business_id', $this->context->current()->value)
            ->where('id', $id->value)
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    /** Every service of the business of the context, active and inactive, ordered by name. */
    public function all(): array
    {
        return array_values(ServiceModel::query()
            ->where('business_id', $this->context->current()->value)
            ->orderBy('name')
            ->get()
            ->map(fn (ServiceModel $model): Service => $this->toDomain($model))
            ->all());
    }

    /** Rebuild the aggregate from its stored state. */
    private function toDomain(ServiceModel $model): Service
    {
        return Service::reconstitute(
            new ServiceId($model->id),
            new BusinessId($model->business_id),
            $model->name,
            (int) $model->duration_minutes,
            new Colones((int) $model->price),
            (int) $model->buffer_minutes,
            (int) $model->max_perfumes,
            (bool) $model->is_active,
        );
    }
}

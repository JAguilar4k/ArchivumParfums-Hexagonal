<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Infrastructure\Persistence;

use Src\Scheduling\Services\Domain\Service;
use Src\Scheduling\Services\Domain\ServiceRepository;
use Src\Scheduling\Services\Infrastructure\Models\ServiceModel;

final class EloquentServiceRepository implements ServiceRepository
{
    public function save(Service $service, string $businessId): void
    {
        ServiceModel::updateOrCreate(
            ['id' => $service->getId()],
            [
                'business_id' => $businessId,
                'name' => $service->getName(),
                'duration' => $service->getDuration(),
                'price' => $service->getPrice(),
                'margin' => $service->getMargin(),
                'max_perfumes' => $service->getMaxPerfumes(),
                'is_active' => $service->isActive(),
            ]
        );
    }

    public function findByIdAndBusiness(
        string $id,
        string $businessId
    ): ?Service {
        $model = ServiceModel::query()
            ->where('id', $id)
            ->where('business_id', $businessId)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function getByBusiness(string $businessId): array
    {
        return ServiceModel::query()
            ->where('business_id', $businessId)
            ->get()
            ->map(fn (ServiceModel $model) => $this->toDomain($model))
            ->all();
    }

    private function toDomain(ServiceModel $model): Service
    {
        return Service::reconstitute(
            $model->id,
            $model->name,
            (int) $model->duration,
            (int) $model->price,
            (int) $model->margin,
            (int) $model->max_perfumes,
            (bool) $model->is_active
        );
    }
}

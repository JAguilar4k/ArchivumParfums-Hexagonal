<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Infrastructure\Persistence;

use Src\Scheduling\Services\Application\Contracts\BookableServices;
use Src\Scheduling\Services\Application\DTOs\BookableService;
use Src\Scheduling\Services\Infrastructure\Persistence\Models\ServiceModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\Colones;

final readonly class EloquentBookableServices implements BookableServices
{
    /** Take the business that the storefront of the address reads. */
    public function __construct(private BusinessContext $context) {}

    /** The active services of the business of the context, ordered by name; the inactive ones are not offered. */
    public function active(): array
    {
        return array_values(ServiceModel::query()
            ->where('business_id', $this->context->current()->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (ServiceModel $model): BookableService => new BookableService(
                $model->id,
                $model->name,
                (int) $model->duration_minutes,
                new Colones((int) $model->price),
                (int) $model->max_perfumes,
            ))
            ->all());
    }
}

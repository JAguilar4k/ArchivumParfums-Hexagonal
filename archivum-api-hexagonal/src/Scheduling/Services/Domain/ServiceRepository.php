<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Domain;

interface ServiceRepository
{
    public function save(Service $service, string $businessId): void;

    public function findByIdAndBusiness(string $id, string $businessId): ?Service;

    public function getByBusiness(string $businessId): array;
}

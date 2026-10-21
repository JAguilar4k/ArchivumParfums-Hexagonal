<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Application;

use Src\Scheduling\Services\Domain\ServiceRepository;

final class ListServices
{
    public function __construct(private readonly ServiceRepository $repository) {}

    public function execute(string $businessId): array
    {
        return $this->repository->getByBusiness($businessId);
    }
}

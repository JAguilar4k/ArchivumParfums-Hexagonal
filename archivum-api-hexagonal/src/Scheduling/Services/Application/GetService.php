<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Application;

use Src\Scheduling\Services\Domain\Service;
use Src\Scheduling\Services\Domain\ServiceRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GetService
{
    public function __construct(private readonly ServiceRepository $repository) {}

    public function execute(string $id, string $businessId): Service
    {
        $service = $this->repository->findByIdAndBusiness($id, $businessId);

        if (! $service) {
            throw new NotFoundHttpException('no-encontrado');
        }

        return $service;
    }
}

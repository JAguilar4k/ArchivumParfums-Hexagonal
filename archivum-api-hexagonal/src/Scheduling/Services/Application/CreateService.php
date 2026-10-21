<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Application;

use Illuminate\Support\Str;
use Src\Scheduling\Services\Domain\Service;
use Src\Scheduling\Services\Domain\ServiceRepository;

final class CreateService
{
    public function __construct(
        private readonly ServiceRepository $repository
    ) {}

    public function execute(string $businessId, array $data): Service
    {
        $service = Service::create(
            (string) Str::uuid(),
            $data['nombre'],
            (int) $data['duracion_minutos'],
            (int) $data['precio'],
            (int) $data['margen_minutos'],
            (int) $data['maximo_perfumes'],

        );

        $this->repository->save($service, $businessId);

        return $service;
    }
}

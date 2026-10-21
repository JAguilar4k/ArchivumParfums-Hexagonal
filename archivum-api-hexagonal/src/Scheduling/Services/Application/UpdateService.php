<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Application;

use Src\Scheduling\Services\Domain\Service;
use Src\Scheduling\Services\Domain\ServiceRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UpdateService
{
    public function __construct(private readonly ServiceRepository $repository) {}

    public function execute(string $id, string $businessId, array $data): Service
    {
        $service = $this->repository->findByIdAndBusiness($id, $businessId);

        if (! $service) {
            throw new NotFoundHttpException('no-encontrado');
        }

        if (isset($data['nombre'])) {
            $service->changeName($data['nombre']);
        }
        if (isset($data['duracion_minutos'])) {
            $service->changeDuration((int) $data['duracion_minutos']);
        }
        if (isset($data['precio'])) {
            $service->changePrice((int) $data['precio']);
        }
        if (isset($data['margen_minutos'])) {
            $service->changeMargin((int) $data['margen_minutos']);
        }
        if (isset($data['maximo_perfumes'])) {
            $service->changeMaxPerfumes((int) $data['maximo_perfumes']);
        }

        if (isset($data['activo'])) {
            $data['activo'] ? $service->activate() : $service->deactivate();
        }

        $this->repository->save($service, $businessId);

        return $service;
    }
}

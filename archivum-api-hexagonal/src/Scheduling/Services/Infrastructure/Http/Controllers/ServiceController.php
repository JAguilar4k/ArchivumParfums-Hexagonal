<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Src\Scheduling\Services\Application\CreateService;
use Src\Scheduling\Services\Application\GetService;
use Src\Scheduling\Services\Application\ListServices;
use Src\Scheduling\Services\Application\UpdateService;
use Src\Scheduling\Services\Infrastructure\Http\Requests\CreateServiceRequest;
use Src\Scheduling\Services\Infrastructure\Http\Requests\UpdateServiceRequest;

class ServiceController extends Controller
{
    public function __construct(
        private readonly CreateService $createService,
        private readonly UpdateService $updateService,
        private readonly ListServices $listServices,
        private readonly GetService $getService
    ) {}

    private function resolveBusinessId(Request $request): string
    {
        $businessId = (string) DB::table('members')
            ->where('user_id', $request->user()->id)
            ->value('business_id');

        if ($businessId !== '') {
            DB::statement("SET LOCAL app.business_id = '{$businessId}'");
        }

        return $businessId;
    }

    public function index(Request $request): JsonResponse
    {
        $businessId = $this->resolveBusinessId($request);

        return response()->json(['data' => $this->listServices->execute($businessId)]);
    }

    public function store(CreateServiceRequest $request): JsonResponse
    {
        $businessId = $this->resolveBusinessId($request);
        $service = $this->createService->execute($businessId, $request->validated());

        return response()->json([
            'id' => $service->getId(),
            'nombre' => $service->getName(),
            'duracion_minutos' => $service->getDuration(),
            'precio' => $service->getPrice(),
            'margen_minutos' => $service->getMargin(),
            'maximo_perfumes' => $service->getMaxPerfumes(),
            'activo' => $service->isActive(),
        ], 201)->header(
            'Location',
            url("/api/v1/panel/servicios/{$service->getId()}")
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $businessId = $this->resolveBusinessId($request);

        return response()->json(['data' => $this->getService->execute($id, $businessId)]);
    }

    public function update(UpdateServiceRequest $request, string $id): JsonResponse
    {
        $businessId = $this->resolveBusinessId($request);
        $service = $this->updateService->execute($id, $businessId, $request->validated());

        return response()->json([
            'id' => $service->getId(),
            'nombre' => $service->getName(),
            'duracion_minutos' => $service->getDuration(),
            'precio' => $service->getPrice(),
            'margen_minutos' => $service->getMargin(),
            'maximo_perfumes' => $service->getMaxPerfumes(),
            'activo' => $service->isActive(),
        ]);
    }
}

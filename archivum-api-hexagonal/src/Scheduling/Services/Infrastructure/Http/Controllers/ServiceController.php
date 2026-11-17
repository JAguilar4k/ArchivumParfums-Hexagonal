<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Scheduling\Services\Application\UseCases\CreateService;
use Src\Scheduling\Services\Application\UseCases\EditService;
use Src\Scheduling\Services\Application\UseCases\ListServices;
use Src\Scheduling\Services\Application\UseCases\ShowService;
use Src\Scheduling\Services\Infrastructure\Http\Requests\PatchService;
use Src\Scheduling\Services\Infrastructure\Http\Requests\StoreService;
use Src\Scheduling\Services\Infrastructure\Http\Resources\PanelService;

final readonly class ServiceController
{
    /** List every service of the business of the session, active and inactive, ordered by name. */
    public function index(ListServices $listServices): AnonymousResourceCollection
    {
        return PanelService::collection($listServices->execute());
    }

    /** Show one service of the business of the session. */
    public function show(string $servicio, ShowService $showService): PanelService
    {
        return new PanelService($showService->execute($servicio));
    }

    /** Register a service in the business of the session. */
    public function store(StoreService $request, CreateService $createService): JsonResponse
    {
        $service = $createService->execute($request->toCommand());

        return (new PanelService($service))
            ->response($request)
            ->setStatusCode(JsonResponse::HTTP_CREATED)
            ->header('Location', rtrim($request->url(), '/').'/'.$service->id->value);
    }

    /** Change the fields sent of a service of the business of the session, also to deactivate or reactivate it. */
    public function update(PatchService $request, string $servicio, EditService $editService): PanelService
    {
        return new PanelService($editService->execute($request->toCommand($servicio)));
    }
}

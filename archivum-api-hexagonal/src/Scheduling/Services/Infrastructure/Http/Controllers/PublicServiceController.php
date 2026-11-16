<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Infrastructure\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Scheduling\Services\Application\UseCases\ListBookableServices;
use Src\Scheduling\Services\Infrastructure\Http\Resources\PublicService;

final readonly class PublicServiceController
{
    /** List the active services of the business of the address, without a session. */
    public function index(ListBookableServices $listBookableServices): AnonymousResourceCollection
    {
        return PublicService::collection($listBookableServices->execute());
    }
}

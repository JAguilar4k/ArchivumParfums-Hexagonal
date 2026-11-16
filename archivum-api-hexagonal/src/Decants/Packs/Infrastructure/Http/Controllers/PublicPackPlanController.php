<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Decants\Packs\Application\UseCases\ListPackPlansOnSale;
use Src\Decants\Packs\Infrastructure\Http\Resources\PublicPackPlan;

final readonly class PublicPackPlanController
{
    /** List the decant packs on sale in the store of the address, by name, to any visitor. */
    public function index(ListPackPlansOnSale $listPlans): AnonymousResourceCollection
    {
        return PublicPackPlan::collection($listPlans->execute());
    }
}

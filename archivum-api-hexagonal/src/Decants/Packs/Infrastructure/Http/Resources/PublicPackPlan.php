<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Decants\Packs\Application\DTOs\PackPlanOnSale;

/**
 * @property-read PackPlanOnSale $resource
 */
final class PublicPackPlan extends JsonResource
{
    /** Wrap an offer on sale as the visitor reads it. */
    public function __construct(private readonly PackPlanOnSale $plan)
    {
        parent::__construct($plan);
    }

    /**
     * Expose what a visitor needs to choose a pack; whether it is on sale goes without saying, since only those are listed.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->plan->id,
            'nombre' => $this->plan->name,
            'cantidad_decants' => $this->plan->decants,
            'ml_por_decant' => $this->plan->mlPerDecant,
            'precio' => $this->plan->price,
            'vigencia_dias' => $this->plan->validityDays,
            'precio_maximo_por_ml' => $this->plan->maxPricePerMl,
        ];
    }
}

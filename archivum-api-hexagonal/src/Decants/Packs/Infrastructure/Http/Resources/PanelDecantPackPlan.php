<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Decants\Packs\Domain\Entities\DecantPackPlan;

/**
 * @property-read DecantPackPlan $resource
 */
final class PanelDecantPackPlan extends JsonResource
{
    /** Wrap an offer aggregate. */
    public function __construct(private readonly DecantPackPlan $plan)
    {
        parent::__construct($plan);
    }

    /**
     * Expose the offer with the vocabulary of the business; only these fields leave the API, never the business.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->plan->id->value,
            'nombre' => $this->plan->name->value,
            'cantidad_decants' => $this->plan->decants->value,
            'ml_por_decant' => $this->plan->mlPerDecant->value,
            'precio' => $this->plan->price->amount,
            'vigencia_dias' => $this->plan->validity->days,
            'precio_maximo_por_ml' => $this->plan->maxPricePerMl?->amount,
            'activo' => $this->plan->isActive,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recommendations\Recommender\Application\DTOs\RecommendedPerfume;

/**
 * @property-read RecommendedPerfume $resource
 */
final class RecommendedPerfumeResource extends JsonResource
{
    /** Wrap a recommended perfume. */
    public function __construct(private readonly RecommendedPerfume $perfume)
    {
        parent::__construct($perfume);
    }

    /**
     * Expose the perfume as the catalog has it, with the reason of the recommender.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->perfume->id,
            'marca' => $this->perfume->brand,
            'nombre' => $this->perfume->name,
            'razon' => $this->perfume->reason,
            'presentaciones' => RecommendedVariantResource::collection($this->perfume->variants),
        ];
    }
}

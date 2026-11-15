<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recommendations\Recommender\Application\DTOs\RecommendedVariant;

/**
 * @property-read RecommendedVariant $resource
 */
final class RecommendedVariantResource extends JsonResource
{
    /** Wrap a presentation of a recommended perfume. */
    public function __construct(private readonly RecommendedVariant $variant)
    {
        parent::__construct($variant);
    }

    /**
     * Expose the presentation with the same fields as the showcase (#48).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ml' => $this->variant->ml,
            'precio_venta' => $this->variant->price,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseDecant;

/**
 * @property-read ShowcaseDecant $resource
 */
final class ShowcaseDecantResource extends JsonResource
{
    /** Wrap a decant size that an open bottle of the perfume can serve. */
    public function __construct(private readonly ShowcaseDecant $decant)
    {
        parent::__construct($decant);
    }

    /**
     * Expose the bottle, the milliliters of its presentation, the milliliters of the decant and the price in whole
     * colones, so the contract types them for archivum-web (#128). The presentation tells apart two offers of the same
     * size from bottles of different presentations, whose prices differ (R3).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'botella_id' => $this->decant->bottleId,
            'presentacion_ml' => $this->decant->presentationMl,
            'ml' => $this->decant->ml,
            'precio' => $this->decant->price,
        ];
    }
}

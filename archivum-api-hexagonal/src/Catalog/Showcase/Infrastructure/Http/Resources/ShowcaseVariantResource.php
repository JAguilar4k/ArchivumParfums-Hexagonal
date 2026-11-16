<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseVariant;

/**
 * @property-read ShowcaseVariant $resource
 */
final class ShowcaseVariantResource extends JsonResource
{
    /** Wrap a presentation of a perfume of the showcase. */
    public function __construct(private readonly ShowcaseVariant $variant)
    {
        parent::__construct($variant);
    }

    /**
     * Expose the milliliters and the sale price in whole colones, so the contract types them for archivum-web (#128).
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

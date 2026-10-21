<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Catalog\Perfumes\Infrastructure\Http\PerfumeVocabulary;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseItem;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseVariant;

/**
 * @property-read ShowcaseItem $resource
 */
final class ShowcasePerfume extends JsonResource
{
    /** Wrap a perfume of the showcase. */
    public function __construct(private readonly ShowcaseItem $item)
    {
        parent::__construct($item);
    }

    /**
     * Expose only what a visitor needs: no business, no state, no cost and no presentation that cannot be sold.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->item->id,
            'marca' => $this->item->brand,
            'nombre' => $this->item->name,
            'concentracion' => $this->item->concentration,
            'familia' => $this->item->family,
            'foto_url' => $this->item->photoUrl,
            'etiquetas' => [
                'momento' => array_map(PerfumeVocabulary::momentOf(...), $this->item->tags['time_of_day']),
                'clima' => array_map(PerfumeVocabulary::climateOf(...), $this->item->tags['climate']),
                'ocasion' => $this->item->tags['occasion'],
            ],
            'presentaciones' => array_map(fn (ShowcaseVariant $variant): array => [
                'ml' => $variant->ml,
                'precio_venta' => $variant->price,
            ], $this->item->variants),
        ];
    }
}

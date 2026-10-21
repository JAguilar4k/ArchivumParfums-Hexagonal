<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Catalog\Perfumes\Infrastructure\Http\PerfumeVocabulary;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseDetail;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseVariant;

/**
 * @property-read ShowcaseDetail $resource
 */
final class ShowcasePerfumeDetail extends JsonResource
{
    /** Wrap the detail of a perfume of the showcase. */
    public function __construct(private readonly ShowcaseDetail $perfume)
    {
        parent::__construct($perfume);
    }

    /**
     * Expose what a visitor needs to decide: no business, no state, no cost and no presentation that cannot be sold.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->perfume->id,
            'marca' => $this->perfume->brand,
            'nombre' => $this->perfume->name,
            'concentracion' => $this->perfume->concentration,
            'genero' => $this->perfume->gender,
            'familia' => $this->perfume->family,
            'descripcion' => $this->perfume->description,
            'foto_url' => $this->perfume->photoUrl,
            'notas' => [
                'salida' => $this->perfume->notes['top'],
                'corazon' => $this->perfume->notes['heart'],
                'fondo' => $this->perfume->notes['base'],
            ],
            'etiquetas' => [
                'momento' => array_map(PerfumeVocabulary::momentOf(...), $this->perfume->tags['time_of_day']),
                'clima' => array_map(PerfumeVocabulary::climateOf(...), $this->perfume->tags['climate']),
                'ocasion' => $this->perfume->tags['occasion'],
            ],
            'presentaciones' => array_map(fn (ShowcaseVariant $variant): array => [
                'ml' => $variant->ml,
                'precio_venta' => $variant->price,
            ], $this->perfume->variants),
        ];
    }
}

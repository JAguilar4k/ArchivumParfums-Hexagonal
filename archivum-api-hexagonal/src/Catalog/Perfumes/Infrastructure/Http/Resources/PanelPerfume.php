<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Catalog\Perfumes\Domain\Entities\Perfume;
use Src\Catalog\Perfumes\Domain\Entities\PerfumeVariant;
use Src\Catalog\Perfumes\Domain\ValueObjects\NoteLayer;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeNote;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeTag;
use Src\Catalog\Perfumes\Domain\ValueObjects\TagKind;
use Src\Catalog\Perfumes\Infrastructure\Http\PerfumeVocabulary;

/**
 * @property-read Perfume $resource
 */
final class PanelPerfume extends JsonResource
{
    /** Wrap a perfume aggregate; the detail also lists its presentations, the other answers do not. */
    public function __construct(private readonly Perfume $perfume, private readonly bool $withVariants = false)
    {
        parent::__construct($perfume);
    }

    /**
     * Expose the perfume with the vocabulary of the business; only these fields leave the API, never the business.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $perfume = [
            'id' => $this->perfume->id->value,
            'marca' => $this->perfume->brand->value,
            'nombre' => $this->perfume->name->value,
            'concentracion' => $this->perfume->details->concentration,
            'genero' => $this->perfume->details->gender,
            'familia' => $this->perfume->details->family,
            'descripcion' => $this->perfume->details->description,
            'notas' => [
                'salida' => $this->notesOf(NoteLayer::Top),
                'corazon' => $this->notesOf(NoteLayer::Heart),
                'fondo' => $this->notesOf(NoteLayer::Base),
            ],
            'etiquetas' => [
                'momento' => array_map(PerfumeVocabulary::momentOf(...), $this->tagsOf(TagKind::TimeOfDay)),
                'clima' => array_map(PerfumeVocabulary::climateOf(...), $this->tagsOf(TagKind::Climate)),
                'ocasion' => $this->tagsOf(TagKind::Occasion),
            ],
            'activo' => $this->perfume->isActive,
        ];

        if ($this->withVariants) {
            $perfume['presentaciones'] = array_map(fn (PerfumeVariant $variant): array => [
                'id' => $variant->id->value,
                'ml' => $variant->size->value,
                'precio_venta' => ($variant->manualPrice ?? $variant->salePrice)?->amount,
                'precio_manual' => $variant->manualPrice?->amount,
                'activa' => $variant->isActive,
            ], $this->perfume->variants);
        }

        return $perfume;
    }

    /**
     * Texts of the notes of one layer.
     *
     * @return list<string>
     */
    private function notesOf(NoteLayer $layer): array
    {
        return array_values(array_map(
            fn (PerfumeNote $note): string => $note->note,
            array_filter($this->perfume->notes, fn (PerfumeNote $note): bool => $note->layer === $layer),
        ));
    }

    /**
     * Values of the tags of one kind.
     *
     * @return list<string>
     */
    private function tagsOf(TagKind $kind): array
    {
        return array_values(array_map(
            fn (PerfumeTag $tag): string => $tag->value,
            array_filter($this->perfume->tags, fn (PerfumeTag $tag): bool => $tag->kind === $kind),
        ));
    }
}

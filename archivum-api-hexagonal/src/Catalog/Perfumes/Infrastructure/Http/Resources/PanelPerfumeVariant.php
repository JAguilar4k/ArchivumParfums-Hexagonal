<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Catalog\Perfumes\Domain\Entities\PerfumeVariant;

/**
 * @property-read PerfumeVariant $resource
 */
final class PanelPerfumeVariant extends JsonResource
{
    /** Wrap a presentation. */
    public function __construct(private readonly PerfumeVariant $variant)
    {
        parent::__construct($variant);
    }

    /**
     * Expose the presentation with the vocabulary of the business; the detail of a perfume lists the same fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return self::fields($this->variant);
    }

    /**
     * The fields of a presentation: the sale price is the manual one when it exists, and null when the presentation has no price yet.
     *
     * @return array{id: string, ml: int, precio_venta: int|null, precio_manual: int|null, activa: bool}
     */
    public static function fields(PerfumeVariant $variant): array
    {
        return [
            'id' => $variant->id->value,
            'ml' => $variant->size->value,
            'precio_venta' => ($variant->manualPrice ?? $variant->salePrice)?->amount,
            'precio_manual' => $variant->manualPrice?->amount,
            'activa' => $variant->isActive,
        ];
    }
}

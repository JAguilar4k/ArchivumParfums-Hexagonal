<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Infrastructure\Http\Resources;

use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Decants\Bottles\Application\DTOs\BottleView;

/** @mixin BottleView */
final class PanelBottle extends JsonResource
{
    public function __construct(private readonly BottleView $bottle)
    {
        parent::__construct($bottle);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->bottle->id,
            'presentacion_id' => $this->bottle->variantId,
            'perfume' => ['id' => $this->bottle->perfumeId, 'marca' => $this->bottle->brand, 'nombre' => $this->bottle->perfumeName],
            'presentacion' => ['id' => $this->bottle->variantId, 'ml' => $this->bottle->variantMl],
            'ml_totales' => $this->bottle->totalMl,
            'ml_restantes' => $this->bottle->remainingMl,
            'ml_reservados' => $this->bottle->reservedMl,
            'ml_disponibles' => $this->bottle->remainingMl - $this->bottle->reservedMl,
            'estado' => $this->bottle->status === 'open' ? 'abierta' : 'vacia',
            'abierta_en' => new DateTimeImmutable($this->bottle->openedAt)->format(DATE_ATOM),
            'notas' => $this->bottle->notes,
        ];
    }
}

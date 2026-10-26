<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Sales\Orders\Domain\Entities\OrderLine;
use Src\Sales\Orders\Domain\ValueObjects\OrderLineKind;

/** @mixin OrderLine */
final class OrderLineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->kind === OrderLineKind::Bottle ? 'botella' : 'decant',
            'presentacion_id' => $this->perfumeVariantId,
            'botella_id' => $this->decantBottleId,
            'perfume' => $this->perfumeName,
            'ml' => $this->ml,
            'presentacion_ml' => $this->variantMl,
            'cantidad' => $this->quantity,
            'precio_unitario' => $this->unitPrice->amount,
            'estado' => $this->status->spanish(),
        ];
    }
}

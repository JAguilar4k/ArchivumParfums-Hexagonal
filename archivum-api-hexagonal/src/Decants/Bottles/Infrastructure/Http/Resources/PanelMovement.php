<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Infrastructure\Http\Resources;

use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Decants\Bottles\Application\DTOs\MovementView;

/** @mixin MovementView */
final class PanelMovement extends JsonResource
{
    public function __construct(private readonly MovementView $movement)
    {
        parent::__construct($movement);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->movement->id,
            'tipo' => match ($this->movement->type) {
                'reservation' => 'reserva', 'release' => 'liberacion', 'consumption' => 'consumo', default => 'ajuste',
            },
            'ml' => $this->movement->ml,
            'motivo' => $this->movement->reason,
            'fecha' => new DateTimeImmutable($this->movement->date)->format(DATE_ATOM),
            'hecho_por' => $this->movement->author,
            'linea_pedido_id' => $this->movement->orderLineId,
        ];
    }
}

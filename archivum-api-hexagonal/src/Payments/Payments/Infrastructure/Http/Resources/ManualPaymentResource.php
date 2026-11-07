<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Payments\Payments\Application\DTOs\ManualPaymentResult;

/** @mixin ManualPaymentResult */
final class ManualPaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'proposito' => match ($this->purpose) {
                'deposit' => 'deposito', 'balance' => 'saldo', default => 'total'
            },
            'monto' => $this->amount,
            'pasarela' => 'manual',
            'estado' => match ($this->status) {
                'succeeded' => 'confirmado', 'refunded' => 'reembolsado', 'partially_refunded' => 'reembolsado_parcialmente', default => 'pendiente',
            },
            'metodo' => $this->method === 'cash' ? 'efectivo' : 'sinpe',
            'referencia' => $this->reference,
            'registrado_por' => ['id' => $this->recordedById, 'nombre' => $this->recordedByName],
            'registrado_en' => $this->recordedAt,
        ];
    }
}

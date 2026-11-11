<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Payments\Payments\Application\DTOs\OrderRefund;

/** @mixin OrderRefund */
final class RefundResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pago' => $this->paymentId,
            'linea' => $this->orderLineId,
            'monto' => $this->amount,
            'estado' => match ($this->status) {
                'succeeded' => 'confirmado', 'failed' => 'fallido', default => 'pendiente',
            },
            'consulta_pendiente' => $this->awaitsAnswer,
        ];
    }
}

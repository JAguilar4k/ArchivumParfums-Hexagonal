<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Payments\Payments\Application\DTOs\PaymentResult;

/** @mixin PaymentResult */
final class PaymentResource extends JsonResource
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
            'moneda_cobrada' => $this->currency,
            'monto_cobrado' => $this->chargedAmount,
            'pasarela' => $this->gateway,
            'estado' => match ($this->status) {
                'succeeded' => 'confirmado', 'failed' => 'fallido', 'expired' => 'vencido', 'refunded' => 'reembolsado', 'partially_refunded' => 'reembolsado_parcialmente', default => 'pendiente',
            },
            'url_pago' => $this->checkoutUrl,
            'medio' => $this->method === 'sinpe_mobile' ? 'sinpe_movil' : 'tarjeta',
            'consulta_pendiente' => $this->communicationError,
        ];
    }
}

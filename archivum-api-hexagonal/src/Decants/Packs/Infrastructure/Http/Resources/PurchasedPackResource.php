<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Decants\Packs\Application\DTOs\PackPayment;
use Src\Decants\Packs\Application\DTOs\PurchasedPack;

/**
 * @property-read PurchasedPack $resource
 */
final class PurchasedPackResource extends JsonResource
{
    /** Wrap a purchase and its payment. */
    public function __construct(private readonly PurchasedPack $purchased)
    {
        parent::__construct($purchased);
    }

    /**
     * Expose the bought pack with the vocabulary of the business, never its business, its customer or its key. While it
     * waits for its payment, vence_en is the deadline of the payment; once active, the end of its validity.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pack = $this->purchased->pack;

        return [
            'id' => $pack->id->value,
            'paquete_id' => $pack->planId->value,
            'estado' => $pack->statusAt(now()->toImmutable())->spanish(),
            'decants_totales' => $pack->totalDecants,
            'decants_restantes' => $pack->remainingDecants,
            'ml_por_decant' => $pack->mlPerDecant,
            'precio_maximo_por_ml' => $pack->maxPricePerMl?->amount,
            'vence_en' => $pack->expiresAt->format(DATE_ATOM),
            'pago' => $this->payment($this->purchased->payment),
        ];
    }

    /**
     * The payment of the full price, with the same fields as a payment of an order (#92).
     *
     * @return array<string, mixed>|null
     */
    private function payment(?PackPayment $payment): ?array
    {
        if ($payment === null) {
            return null;
        }

        return [
            'id' => $payment->id,
            'monto' => $payment->amount,
            'moneda_cobrada' => $payment->currency,
            'monto_cobrado' => $payment->chargedAmount,
            'pasarela' => $payment->gateway,
            'estado' => match ($payment->status) {
                'succeeded' => 'confirmado', 'failed' => 'fallido', 'expired' => 'vencido', 'refunded' => 'reembolsado', 'partially_refunded' => 'reembolsado_parcialmente', default => 'pendiente',
            },
            'url_pago' => $payment->checkoutUrl,
            'medio' => $payment->method === 'sinpe_mobile' ? 'sinpe_movil' : 'tarjeta',
            'consulta_pendiente' => $payment->communicationError,
        ];
    }
}

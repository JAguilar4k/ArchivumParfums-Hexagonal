<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Decants\Packs\Application\DTOs\SoldPackView;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;

/**
 * @property-read SoldPackView $resource
 */
final class PanelSoldPack extends JsonResource
{
    /** Wrap a pack sold in the store as its owner reads it. */
    public function __construct(private readonly SoldPackView $pack)
    {
        parent::__construct($pack);
    }

    /**
     * Expose the balance and the expiry of the pack with its offer; of the customer only the name, never the email or the
     * telephone (#110). While the pack waits for its payment, vence_en is the deadline of the payment.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->pack->id,
            'cliente' => ['nombre' => $this->pack->customerName],
            'oferta' => ['id' => $this->pack->planId, 'nombre' => $this->pack->planName],
            'decants_totales' => $this->pack->totalDecants,
            'decants_restantes' => $this->pack->remainingDecants,
            'ml_por_decant' => $this->pack->mlPerDecant,
            'estado' => DecantPackStatus::from($this->pack->status)->spanish(),
            'vence_en' => $this->pack->expiresAt,
        ];
    }
}

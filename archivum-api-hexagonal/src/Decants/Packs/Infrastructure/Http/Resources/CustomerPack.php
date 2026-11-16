<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Decants\Packs\Application\DTOs\PurchasedPackView;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;

/**
 * @property-read PurchasedPackView $resource
 */
final class CustomerPack extends JsonResource
{
    /** Wrap a pack as the customer who bought it reads it. */
    public function __construct(private readonly PurchasedPackView $pack)
    {
        parent::__construct($pack);
    }

    /**
     * Expose the balance and the expiry of the pack with the store and the offer it belongs to; the slug of the store is
     * the one its addresses use, as in the order where the pack is redeemed. While the pack waits for its payment,
     * vence_en is the deadline of the payment; afterwards, the end of its validity.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->pack->id,
            'tienda' => ['id' => $this->pack->businessId, 'nombre' => $this->pack->businessName, 'slug' => $this->pack->businessSlug],
            'oferta' => ['id' => $this->pack->planId, 'nombre' => $this->pack->planName],
            'decants_totales' => $this->pack->totalDecants,
            'decants_restantes' => $this->pack->remainingDecants,
            'ml_por_decant' => $this->pack->mlPerDecant,
            'estado' => DecantPackStatus::from($this->pack->status)->spanish(),
            'vence_en' => $this->pack->expiresAt,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Src\Businesses\PublicProfile\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Businesses\PublicProfile\Application\DTOs\PublicBusinessView;

/**
 * @property-read PublicBusinessView $resource
 */
final class PublicBusiness extends JsonResource
{
    /** Wrap the public view of a business. */
    public function __construct(private readonly PublicBusinessView $business)
    {
        parent::__construct($business);
    }

    /**
     * Expose only these fields: no settings, no staff and no internal identifier ever leave through here.
     *
     * @return array{nombre: string, slug: string, logo_url: string|null, lugar_cita: string|null, zona_horaria: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'nombre' => $this->business->name,
            'slug' => $this->business->slug,
            'logo_url' => $this->business->logoUrl,
            'lugar_cita' => $this->business->appointmentPlace,
            'zona_horaria' => $this->business->timezone,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Businesses\Profile\Domain\Entities\Business;

/**
 * @property-read Business $resource
 */
final class PlatformBusinessResource extends JsonResource
{
    /** Wrap a business aggregate. */
    public function __construct(private readonly Business $business)
    {
        parent::__construct($business);
    }

    /**
     * Expose what the administrator of the platform sees of a business: its identity and its public address, never
     * its settings, staff or customers.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->business->id->value,
            'nombre' => $this->business->name->value,
            'slug' => $this->business->slug->value,
            'zona_horaria' => $this->business->timezone->value,
        ];
    }
}

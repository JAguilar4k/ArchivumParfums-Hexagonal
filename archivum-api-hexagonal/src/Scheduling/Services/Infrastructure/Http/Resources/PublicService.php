<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Scheduling\Services\Application\DTOs\BookableService;

/**
 * @property-read BookableService $resource
 */
final class PublicService extends JsonResource
{
    /** Wrap a service of the public storefront. */
    public function __construct(private readonly BookableService $service)
    {
        parent::__construct($service);
    }

    /**
     * Expose only the fields of the public contract: the margin and the internal data of the service never leave the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->service->id,
            'nombre' => $this->service->name,
            'duracion_minutos' => $this->service->durationMinutes,
            'precio' => $this->service->price->amount,
            'maximo_perfumes' => $this->service->maxPerfumes,
        ];
    }
}

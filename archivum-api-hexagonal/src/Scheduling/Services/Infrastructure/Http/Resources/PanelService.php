<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Scheduling\Services\Domain\Entities\Service;

/**
 * @property-read Service $resource
 */
final class PanelService extends JsonResource
{
    /** Wrap a service of the business of the session. */
    public function __construct(private readonly Service $service)
    {
        parent::__construct($service);
    }

    /**
     * Expose the service with the vocabulary of the business; the business itself never leaves the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->service->id->value,
            'nombre' => $this->service->name,
            'duracion_minutos' => $this->service->durationMinutes,
            'precio' => $this->service->price->amount,
            'margen_minutos' => $this->service->bufferMinutes,
            'maximo_perfumes' => $this->service->maxPerfumes,
            'activo' => $this->service->isActive(),
        ];
    }
}

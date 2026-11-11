<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Scheduling\Appointments\Application\DTOs\AppointmentView;

/**
 * @property-read AppointmentView $resource
 */
final class CustomerAppointmentResource extends JsonResource
{
    /** The state of the appointment as the customer reads it, from the table of #9. */
    public const array STATUSES = [
        'confirmed' => 'confirmada',
        'held' => 'apartada',
        'cancelled' => 'cancelada',
        'completed' => 'atendida',
        'no_show' => 'no_asistio',
        'expired' => 'vencida',
    ];

    /** Wrap one appointment of the customer. */
    public function __construct(private readonly AppointmentView $appointment)
    {
        parent::__construct($appointment);
    }

    /**
     * Expose the appointment as the customer reads it: the store, the state in Spanish, the service, the person, the
     * two ends with the offset of the store, the place and the perfumes to try.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->appointment->id,
            'tienda' => ['id' => $this->appointment->businessId, 'nombre' => $this->appointment->businessName],
            'estado' => self::STATUSES[$this->appointment->status] ?? $this->appointment->status,
            'servicio' => ['id' => $this->appointment->serviceId, 'nombre' => $this->appointment->serviceName],
            'persona' => ['id' => $this->appointment->memberId, 'nombre' => $this->appointment->memberName],
            'inicia_en' => $this->appointment->startsAt->format(DATE_ATOM),
            'termina_en' => $this->appointment->endsAt->format(DATE_ATOM),
            'lugar_cita' => $this->appointment->place,
            'perfumes' => array_map(
                fn (array $perfume): array => ['id' => $perfume['id'], 'nombre' => $perfume['name']],
                $this->appointment->perfumes,
            ),
        ];
    }
}

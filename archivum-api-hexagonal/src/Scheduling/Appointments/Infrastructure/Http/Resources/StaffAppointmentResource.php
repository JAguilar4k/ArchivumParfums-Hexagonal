<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Scheduling\Appointments\Application\DTOs\StaffAppointmentView;

/**
 * @property-read StaffAppointmentView $resource
 */
final class StaffAppointmentResource extends JsonResource
{
    /** The state of the appointment as the panel reads it, from the table of #9. */
    public const array STATUSES = CustomerAppointmentResource::STATUSES;

    /** Wrap one appointment of the agenda. */
    public function __construct(private readonly StaffAppointmentView $appointment)
    {
        parent::__construct($appointment);
    }

    /**
     * Expose the appointment as the staff reads it: the customer with the name the minimization of #110 allows, the
     * service, the person who attends it, the two ends with the offset of the store, the state in Spanish and the
     * perfumes to carry with their brand and name.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->appointment->id,
            'estado' => self::STATUSES[$this->appointment->status] ?? $this->appointment->status,
            'cliente' => ['id' => $this->appointment->customerId, 'nombre' => $this->appointment->customerName],
            'servicio' => ['id' => $this->appointment->serviceId, 'nombre' => $this->appointment->serviceName],
            'persona' => ['id' => $this->appointment->memberId, 'nombre' => $this->appointment->memberName],
            'inicia_en' => $this->appointment->startsAt->format(DATE_ATOM),
            'termina_en' => $this->appointment->endsAt->format(DATE_ATOM),
            'perfumes' => array_map(
                fn (array $perfume): array => ['id' => $perfume['id'], 'marca' => $perfume['brand'], 'nombre' => $perfume['name']],
                $this->appointment->perfumes,
            ),
        ];
    }
}

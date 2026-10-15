<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Businesses\Profile\Domain\Entities\Business;

/**
 * @property-read Business $resource
 */
final class BusinessProfile extends JsonResource
{
    /** Wrap a business aggregate. */
    public function __construct(private readonly Business $business)
    {
        parent::__construct($business);
    }

    /**
     * Expose the profile and the editable settings with the vocabulary of the business.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = $this->business->settings;

        return [
            'id' => $this->business->id->value,
            'nombre' => $this->business->name->value,
            'slug' => $this->business->slug->value,
            'lugar_cita' => $this->business->appointmentPlace->value === '' ? null : $this->business->appointmentPlace->value,
            'zona_horaria' => $this->business->timezone->value,
            'ajustes' => [
                'margen_botella' => $settings->bottleMarginColones,
                'tamanos_decant_ml' => $settings->decantSizesMl,
                'anticipacion_minima_horas' => $settings->minimumNoticeHours,
                'horizonte_reserva_dias' => $settings->bookingHorizonDays,
                'plazo_cambio_cita_horas' => $settings->appointmentChangeCutoffHours,
                'apartado_lista_espera_horas' => $settings->waitlistHoldHours,
                'vencimiento_sin_pago_minutos' => $settings->unpaidExpiryMinutes,
                'recordatorios_horas' => $settings->reminderHours,
                'pasarela_por_defecto' => $settings->defaultGateway,
                'tipo_cambio_usd' => $settings->usdExchangeRate,
            ],
        ];
    }
}

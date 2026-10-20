<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Infrastructure\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Src\Businesses\Profile\Application\DTOs\UpdateBusinessProfileCommand;
use Src\Businesses\Profile\Application\UseCases\GetBusinessProfile;
use Src\Businesses\Profile\Domain\ValueObjects\AppointmentPlace;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessName;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSettings;
use Src\Shared\Domain\Contracts\BusinessContext;
use Stringable;

final class UpdateBusinessProfileRequest extends FormRequest
{
    /**
     * Profile fields of the payload paired with the names the use case expects.
     *
     * @var array<string, string>
     */
    private const array FIELDS = [
        'nombre' => 'name',
        'lugar_cita' => 'appointment_place',
        'zona_horaria' => 'timezone',
    ];

    /**
     * Settings of the payload paired with the names the use case expects. Any other key of ajustes is ignored.
     *
     * @var array<string, string>
     */
    private const array SETTINGS = [
        'margen_botella' => 'bottle_margin_colones',
        'tamanos_decant_ml' => 'decant_sizes_ml',
        'anticipacion_minima_horas' => 'minimum_notice_hours',
        'horizonte_reserva_dias' => 'booking_horizon_days',
        'plazo_cambio_cita_horas' => 'appointment_change_cutoff_hours',
        'apartado_lista_espera_horas' => 'waitlist_hold_hours',
        'vencimiento_sin_pago_minutos' => 'unpaid_expiry_minutes',
        'recordatorios_horas' => 'reminder_hours',
        'pasarela_por_defecto' => 'default_gateway',
        'tipo_cambio_usd' => 'usd_exchange_rate',
    ];

    /**
     * Validation rules for a partial update of the profile and the settings.
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(): array
    {
        $amount = ['sometimes', 'required', 'integer', 'min:0'];

        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:'.BusinessName::MAX_LENGTH],
            'lugar_cita' => ['sometimes', 'nullable', 'string', 'max:'.AppointmentPlace::MAX_LENGTH],
            'zona_horaria' => ['sometimes', 'required', 'string', 'timezone:all'],
            'ajustes' => ['sometimes', 'array'],
            'ajustes.margen_botella' => $amount,
            'ajustes.tamanos_decant_ml' => ['sometimes', 'array', 'list', 'min:1'],
            'ajustes.tamanos_decant_ml.*' => ['required', 'integer', 'min:1', 'distinct'],
            'ajustes.anticipacion_minima_horas' => $amount,
            'ajustes.horizonte_reserva_dias' => $amount,
            'ajustes.plazo_cambio_cita_horas' => $amount,
            'ajustes.apartado_lista_espera_horas' => $amount,
            'ajustes.vencimiento_sin_pago_minutos' => $amount,
            'ajustes.recordatorios_horas' => ['sometimes', 'array', 'list'],
            'ajustes.recordatorios_horas.*' => ['required', 'integer', 'min:0'],
            'ajustes.pasarela_por_defecto' => ['sometimes', 'required', 'string', Rule::in(BusinessSettings::GATEWAYS)],
            'ajustes.tipo_cambio_usd' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
        ];
    }

    /**
     * Check the booking horizon against the minimum notice, taking the stored value of the one not sent.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(BusinessContext $businessContext, GetBusinessProfile $getProfile): array
    {
        return [function (Validator $validator) use ($businessContext, $getProfile): void {
            $notice = $this->input('ajustes.anticipacion_minima_horas');
            $horizon = $this->input('ajustes.horizonte_reserva_dias');

            if (($notice === null && $horizon === null)
                || $validator->errors()->hasAny(['ajustes', 'ajustes.anticipacion_minima_horas', 'ajustes.horizonte_reserva_dias'])) {
                return;
            }

            $stored = $getProfile->execute($businessContext->current()->value)->settings;

            if ((int) ($horizon ?? $stored->bookingHorizonDays) * 24 < (int) ($notice ?? $stored->minimumNoticeHours)) {
                $validator->errors()->add(
                    $horizon === null ? 'ajustes.anticipacion_minima_horas' : 'ajustes.horizonte_reserva_dias',
                    __('businesses.horizon_before_notice'),
                );
            }
        }];
    }

    /** Map the validated payload to the use case input; the business comes from the session, never from the payload. */
    public function toCommand(BusinessContext $businessContext): UpdateBusinessProfileCommand
    {
        $validated = $this->validated();
        $changes = [];

        foreach (self::FIELDS as $field => $name) {
            if (array_key_exists($field, $validated)) {
                // A null place clears it: the business has no place configured.
                $changes[$name] = $field === 'lugar_cita' && $validated[$field] === null ? null : (string) $validated[$field];
            }
        }

        $settings = [];

        foreach (self::SETTINGS as $field => $name) {
            if (Arr::has($validated, "ajustes.{$field}")) {
                $value = Arr::get($validated, "ajustes.{$field}");

                // Each setting takes the type the use case expects, so a number sent as text arrives as a number.
                if ($name === 'decant_sizes_ml' || $name === 'reminder_hours') {
                    $settings[$name] = array_values(array_map(intval(...), (array) $value));
                } elseif ($name === 'default_gateway') {
                    $settings[$name] = (string) $value;
                } elseif ($name === 'usd_exchange_rate') {
                    $settings[$name] = $value === null ? null : (float) $value;
                } else {
                    $settings[$name] = (int) $value;
                }
            }
        }

        if ($settings !== []) {
            $changes['settings'] = $settings;
        }

        return new UpdateBusinessProfileCommand($businessContext->current()->value, $changes);
    }
}

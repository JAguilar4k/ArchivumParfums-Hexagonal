<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Scheduling\Appointments\Infrastructure\Http\Resources\StaffAppointmentResource;

final class IndexStaffAgenda extends FormRequest
{
    /** The most days one request of the agenda may cover, a technical bound of the query and not a rule of the business. */
    public const int MAX_DAYS = 31;

    /**
     * The filters of the agenda: a day or a range, the person, the state and the page.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'desde' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'required_with:hasta'],
            'hasta' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'required_with:desde'],
            'persona' => ['sometimes', 'nullable', 'uuid'],
            'estado' => ['sometimes', 'nullable', Rule::in(array_values(StaffAppointmentResource::STATUSES))],
        ];
    }

    /**
     * Names of the query parameters in the messages of the errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['fecha' => 'fecha', 'desde' => 'desde', 'hasta' => 'hasta', 'persona' => 'persona', 'estado' => 'estado'];
    }

    /** Refuse a range that ends before it starts or that covers more days than the agenda allows. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->from();
            $to = $this->to();

            if ($validator->errors()->isNotEmpty() || $from === null || $to === null) {
                return;
            }

            if ($to < $from) {
                $validator->errors()->add('hasta', __('scheduling.agenda.invalid_range'));

                return;
            }

            if ($this->days($from, $to) > self::MAX_DAYS) {
                $validator->errors()->add('hasta', __('scheduling.agenda.range_too_long'));
            }
        });
    }

    /** First day of the window: `desde`, or the day of `fecha`; null when the panel asked for no day. */
    public function from(): ?string
    {
        $desde = $this->query('desde');

        if (is_string($desde) && $desde !== '') {
            return $desde;
        }

        $fecha = $this->query('fecha');

        return is_string($fecha) && $fecha !== '' ? $fecha : null;
    }

    /** Last day of the window: `hasta`, or the same day the window starts; null when the panel asked for no day. */
    public function to(): ?string
    {
        $hasta = $this->query('hasta');

        if (is_string($hasta) && $hasta !== '') {
            return $hasta;
        }

        return $this->from();
    }

    /** The person the owner narrows the agenda to; a collaborator never uses it, because the identity comes from the session. */
    public function memberId(): ?string
    {
        $persona = $this->query('persona');

        return is_string($persona) && $persona !== '' ? $persona : null;
    }

    /** The state of the appointment as the panel reads it, translated to the name the domain and the table keep. */
    public function status(): ?string
    {
        $estado = $this->query('estado');

        if (! is_string($estado) || $estado === '') {
            return null;
        }

        $english = array_search($estado, StaffAppointmentResource::STATUSES, true);

        return is_string($english) ? $english : null;
    }

    /** Days the window covers, both ends included. */
    private function days(string $from, string $to): int
    {
        $start = new \DateTimeImmutable($from);
        $end = new \DateTimeImmutable($to);

        return (int) $start->diff($end)->days + 1;
    }
}

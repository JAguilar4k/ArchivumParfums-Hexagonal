<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Scheduling\Appointments\Application\DTOs\RescheduleAppointmentCommand;

final class RescheduleAppointmentRequest extends FormRequest
{
    /**
     * The body of a reschedule accepts only the new start; any other field is ignored on purpose.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'inicia_en' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}:\d{2})$/'],
        ];
    }

    /**
     * Names of the fields in the messages of the errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['inicia_en' => 'inicio de la cita'];
    }

    /** Map the validated body to the use case input. */
    public function toCommand(string $appointmentId): RescheduleAppointmentCommand
    {
        return new RescheduleAppointmentCommand($appointmentId, $this->string('inicia_en')->toString());
    }
}

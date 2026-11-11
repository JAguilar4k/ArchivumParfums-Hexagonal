<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Scheduling\Appointments\Application\DTOs\CancelAppointmentCommand;

final class CancelAppointmentRequest extends FormRequest
{
    /**
     * The body of a cancellation only carries the reason; the appointment and the store come from the route.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }

    /**
     * Names of the fields in the messages of the errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['motivo' => 'motivo'];
    }

    /** Map the validated body to the use case input. */
    public function toCommand(string $appointmentId, bool $customerDeadline): CancelAppointmentCommand
    {
        $reason = $this->validated('motivo');

        return new CancelAppointmentCommand($appointmentId, is_string($reason) ? $reason : null, $customerDeadline);
    }
}

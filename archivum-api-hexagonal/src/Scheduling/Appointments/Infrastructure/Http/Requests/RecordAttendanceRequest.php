<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Scheduling\Appointments\Application\DTOs\RecordAttendanceCommand;

final class RecordAttendanceRequest extends FormRequest
{
    /**
     * The attendance is required and boolean: a missing value or one that is not a boolean is a 422.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'asistio' => ['required', 'boolean'],
        ];
    }

    /**
     * Names of the fields in the messages of the errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['asistio' => 'asistencia'];
    }

    /** The command of the use case, with the attendance the panel sent. */
    public function toCommand(string $appointmentId): RecordAttendanceCommand
    {
        return new RecordAttendanceCommand($appointmentId, $this->boolean('asistio'));
    }
}

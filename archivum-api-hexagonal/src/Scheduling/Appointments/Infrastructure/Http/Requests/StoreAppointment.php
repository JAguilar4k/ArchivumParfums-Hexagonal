<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Scheduling\Appointments\Application\DTOs\BookAppointmentCommand;

final class StoreAppointment extends FormRequest
{
    /**
     * The body of a booking: the service, the instant with its offset, the person when the customer chooses one and the
     * perfumes to try. Everything else is derived from the store.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'servicio_id' => ['required', 'uuid'],
            'inicia_en' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}:\d{2})$/'],
            'persona_id' => ['sometimes', 'nullable', 'uuid'],
            'perfumes' => ['sometimes', 'array'],
            'perfumes.*' => ['uuid'],
        ];
    }

    /**
     * Names of the fields in the messages of the errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'servicio_id' => 'servicio',
            'inicia_en' => 'inicio de la cita',
            'persona_id' => 'persona',
            'perfumes' => 'perfumes',
            'perfumes.*' => 'perfume',
        ];
    }

    /** Map the validated body to the use case input, taking the account from the session and never from the browser. */
    public function toCommand(): BookAppointmentCommand
    {
        /** @var list<string> $perfumes */
        $perfumes = $this->validated('perfumes', []);

        return new BookAppointmentCommand(
            (string) $this->user()?->getAuthIdentifier(),
            $this->string('servicio_id')->toString(),
            $this->string('inicia_en')->toString(),
            $this->filled('persona_id') ? $this->string('persona_id')->toString() : null,
            $perfumes,
        );
    }
}

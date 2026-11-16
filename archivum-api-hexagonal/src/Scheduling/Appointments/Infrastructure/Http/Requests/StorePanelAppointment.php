<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Scheduling\Appointments\Application\DTOs\BookAppointmentCommand;

final class StorePanelAppointment extends FormRequest
{
    /**
     * The body of a booking the panel makes for a customer: the customer record of this store, the service, the
     * instant with its offset, the person when the owner chooses one and the perfumes.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'uuid'],
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
            'cliente_id' => 'cliente',
            'servicio_id' => 'servicio',
            'inicia_en' => 'inicio de la cita',
            'persona_id' => 'persona',
            'perfumes' => 'perfumes',
            'perfumes.*' => 'perfume',
        ];
    }

    /** Map the validated body to the use case input; the store always comes from the session, never from the body. */
    public function toCommand(): BookAppointmentCommand
    {
        /** @var list<string> $perfumes */
        $perfumes = $this->validated('perfumes', []);

        return new BookAppointmentCommand(
            userId: null,
            serviceId: $this->string('servicio_id')->toString(),
            startsAt: $this->string('inicia_en')->toString(),
            memberId: $this->filled('persona_id') ? $this->string('persona_id')->toString() : null,
            perfumes: $perfumes,
            customerId: $this->string('cliente_id')->toString(),
        );
    }
}

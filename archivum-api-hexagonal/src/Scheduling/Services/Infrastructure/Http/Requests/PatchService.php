<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Scheduling\Services\Application\DTOs\EditServiceCommand;
use Src\Shared\Domain\ValueObjects\Colones;
use Stringable;

final class PatchService extends FormRequest
{
    /**
     * The same rules as the creation, but every field is optional.
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(): array
    {
        return ServiceFields::rules(partial: true);
    }

    /**
     * Names of the fields in the messages of the errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ServiceFields::attributes();
    }

    /** Map the validated payload to the use case input; only what was sent is a change, and the business is not part of it. */
    public function toCommand(string $serviceId): EditServiceCommand
    {
        $validated = $this->validated();
        $changes = [];

        if (isset($validated['nombre'])) {
            $changes['name'] = (string) $validated['nombre'];
        }

        if (isset($validated['duracion_minutos'])) {
            $changes['duration_minutes'] = (int) $validated['duracion_minutos'];
        }

        if (isset($validated['precio'])) {
            $changes['price'] = new Colones((int) $validated['precio']);
        }

        if (isset($validated['margen_minutos'])) {
            $changes['buffer_minutes'] = (int) $validated['margen_minutos'];
        }

        if (isset($validated['maximo_perfumes'])) {
            $changes['max_perfumes'] = (int) $validated['maximo_perfumes'];
        }

        if (array_key_exists('activo', $validated)) {
            $changes['active'] = (bool) $validated['activo'];
        }

        return new EditServiceCommand($serviceId, $changes);
    }
}

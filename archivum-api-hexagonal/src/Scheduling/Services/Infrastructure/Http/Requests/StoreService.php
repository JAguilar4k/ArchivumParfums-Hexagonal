<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Src\Scheduling\Services\Application\DTOs\CreateServiceCommand;
use Src\Shared\Domain\ValueObjects\Colones;
use Stringable;

final class StoreService extends FormRequest
{
    /**
     * Rules for a new service; the business is never read from the body, it comes from the session.
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(): array
    {
        return ServiceFields::rules(partial: false);
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

    /** Map the validated payload to the use case input. */
    public function toCommand(): CreateServiceCommand
    {
        $validated = $this->validated();

        return new CreateServiceCommand(
            name: (string) $validated['nombre'],
            durationMinutes: (int) $validated['duracion_minutos'],
            price: new Colones((int) $validated['precio']),
            bufferMinutes: (int) $validated['margen_minutos'],
            maxPerfumes: (int) $validated['maximo_perfumes'],
            isActive: (bool) Arr::get($validated, 'activo', true),
        );
    }
}

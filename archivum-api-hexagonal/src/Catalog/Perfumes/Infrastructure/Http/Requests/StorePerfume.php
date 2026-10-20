<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Src\Catalog\Perfumes\Application\DTOs\CreatePerfumeCommand;
use Src\Catalog\Perfumes\Infrastructure\Http\PerfumeVocabulary;
use Stringable;

final class StorePerfume extends FormRequest
{
    /**
     * Validation rules for a new perfume; only the brand and the name are required, and the business is never read from the body.
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(): array
    {
        return PerfumeFields::rules(partial: false);
    }

    /**
     * Messages in Spanish for the values that the rules of Laravel cannot name.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return PerfumeFields::messages();
    }

    /**
     * Names of the fields in the messages of the errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return PerfumeFields::attributes();
    }

    /** Map the validated payload to the use case input. */
    public function toCommand(): CreatePerfumeCommand
    {
        $validated = $this->validated();

        return new CreatePerfumeCommand(
            brand: (string) $validated['marca'],
            name: (string) $validated['nombre'],
            concentration: Arr::get($validated, 'concentracion'),
            gender: Arr::get($validated, 'genero'),
            family: Arr::get($validated, 'familia'),
            description: Arr::get($validated, 'descripcion'),
            notes: [
                'top' => PerfumeFields::texts($validated, 'notas.salida') ?? [],
                'heart' => PerfumeFields::texts($validated, 'notas.corazon') ?? [],
                'base' => PerfumeFields::texts($validated, 'notas.fondo') ?? [],
            ],
            tags: [
                'time_of_day' => PerfumeFields::texts($validated, 'etiquetas.momento', PerfumeVocabulary::MOMENTS) ?? [],
                'climate' => PerfumeFields::texts($validated, 'etiquetas.clima', PerfumeVocabulary::CLIMATES) ?? [],
                'occasion' => PerfumeFields::texts($validated, 'etiquetas.ocasion') ?? [],
            ],
            isActive: (bool) Arr::get($validated, 'activo', true),
        );
    }
}

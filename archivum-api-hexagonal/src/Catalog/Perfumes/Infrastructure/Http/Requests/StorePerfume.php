<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Src\Catalog\Perfumes\Application\DTOs\CreatePerfumeCommand;
use Src\Catalog\Perfumes\Domain\ValueObjects\Brand;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeDetails;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeName;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeNote;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeTag;
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
        $note = ['required', 'string', 'max:'.PerfumeNote::MAX_LENGTH, 'distinct:ignore_case'];
        $list = ['sometimes', 'nullable', 'array', 'list'];

        return [
            'marca' => ['required', 'string', 'max:'.Brand::MAX_LENGTH],
            'nombre' => ['required', 'string', 'max:'.PerfumeName::MAX_LENGTH],
            'concentracion' => ['sometimes', 'nullable', 'string', 'max:'.PerfumeDetails::MAX_CONCENTRATION_LENGTH],
            'genero' => ['sometimes', 'nullable', 'string', 'max:'.PerfumeDetails::MAX_GENDER_LENGTH],
            'familia' => ['sometimes', 'nullable', 'string', 'max:'.PerfumeDetails::MAX_FAMILY_LENGTH],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:'.PerfumeDetails::MAX_DESCRIPTION_LENGTH],
            'notas' => ['sometimes', 'nullable', 'array'],
            'notas.salida' => $list,
            'notas.salida.*' => $note,
            'notas.corazon' => $list,
            'notas.corazon.*' => $note,
            'notas.fondo' => $list,
            'notas.fondo.*' => $note,
            'etiquetas' => ['sometimes', 'nullable', 'array'],
            'etiquetas.momento' => $list,
            'etiquetas.momento.*' => ['required', 'string', Rule::in(array_keys(PerfumeVocabulary::MOMENTS)), 'distinct'],
            'etiquetas.clima' => $list,
            'etiquetas.clima.*' => ['required', 'string', Rule::in(array_keys(PerfumeVocabulary::CLIMATES)), 'distinct'],
            'etiquetas.ocasion' => $list,
            'etiquetas.ocasion.*' => ['required', 'string', 'max:'.PerfumeTag::MAX_OCCASION_LENGTH, 'distinct:ignore_case'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Messages in Spanish for the values that the rules of Laravel cannot name.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'etiquetas.momento.*.in' => __('catalog.time_of_day_invalid'),
            'etiquetas.clima.*.in' => __('catalog.climate_invalid'),
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
            'marca' => 'marca',
            'nombre' => 'nombre',
            'concentracion' => 'concentración',
            'genero' => 'género',
            'familia' => 'familia olfativa',
            'descripcion' => 'descripción',
            'notas' => 'notas',
            'notas.salida' => 'notas de salida',
            'notas.salida.*' => 'nota de salida',
            'notas.corazon' => 'notas de corazón',
            'notas.corazon.*' => 'nota de corazón',
            'notas.fondo' => 'notas de fondo',
            'notas.fondo.*' => 'nota de fondo',
            'etiquetas' => 'etiquetas',
            'etiquetas.momento' => 'momentos del día',
            'etiquetas.momento.*' => 'momento del día',
            'etiquetas.clima' => 'climas',
            'etiquetas.clima.*' => 'clima',
            'etiquetas.ocasion' => 'ocasiones',
            'etiquetas.ocasion.*' => 'ocasión',
            'activo' => 'activo',
        ];
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
                'top' => $this->texts($validated, 'notas.salida'),
                'heart' => $this->texts($validated, 'notas.corazon'),
                'base' => $this->texts($validated, 'notas.fondo'),
            ],
            tags: [
                'time_of_day' => array_map(
                    fn (string $moment): string => PerfumeVocabulary::MOMENTS[$moment],
                    $this->texts($validated, 'etiquetas.momento'),
                ),
                'climate' => array_map(
                    fn (string $climate): string => PerfumeVocabulary::CLIMATES[$climate],
                    $this->texts($validated, 'etiquetas.clima'),
                ),
                'occasion' => $this->texts($validated, 'etiquetas.ocasion'),
            ],
            isActive: (bool) Arr::get($validated, 'activo', true),
        );
    }

    /**
     * Texts sent under a key, or none when the key is absent or null.
     *
     * @param  array<string, mixed>  $validated
     * @return list<string>
     */
    private function texts(array $validated, string $key): array
    {
        return array_values(array_map(strval(...), (array) Arr::get($validated, $key, [])));
    }
}

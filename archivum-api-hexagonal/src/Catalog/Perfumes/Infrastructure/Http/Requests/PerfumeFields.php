<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Http\Requests;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Src\Catalog\Perfumes\Domain\ValueObjects\Brand;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeDetails;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeName;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeNote;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeTag;
use Src\Catalog\Perfumes\Infrastructure\Http\PerfumeVocabulary;
use Stringable;

/** The rules, messages and names of the fields of a perfume, shared by the request that creates it and the one that edits it. */
final class PerfumeFields
{
    /**
     * Validation rules; when partial, the brand and the name are checked only if they are sent.
     *
     * @return array<string, list<Stringable|string>>
     */
    public static function rules(bool $partial): array
    {
        $note = ['required', 'string', 'max:'.PerfumeNote::MAX_LENGTH, 'distinct:ignore_case'];
        $list = ['sometimes', 'nullable', 'array', 'list'];
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'marca' => [...$required, 'string', 'max:'.Brand::MAX_LENGTH],
            'nombre' => [...$required, 'string', 'max:'.PerfumeName::MAX_LENGTH],
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
    public static function messages(): array
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
    public static function attributes(): array
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

    /**
     * Texts sent under a key, with the words of the API changed to the stored ones; null when the key was not sent.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<string, string>  $words  Stored value by word of the API, for the keys that have a vocabulary.
     * @return list<string>|null
     */
    public static function texts(array $validated, string $key, array $words = []): ?array
    {
        if (! Arr::has($validated, $key)) {
            return null;
        }

        return array_values(array_map(
            fn (mixed $text): string => $words[(string) $text] ?? (string) $text,
            (array) Arr::get($validated, $key),
        ));
    }
}

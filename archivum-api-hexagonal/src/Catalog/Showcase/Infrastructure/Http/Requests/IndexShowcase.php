<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Catalog\Perfumes\Domain\ValueObjects\Brand;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeDetails;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeNote;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeTag;
use Src\Catalog\Perfumes\Infrastructure\Http\PerfumeVocabulary;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseFilters;
use Stringable;

final class IndexShowcase extends FormRequest
{
    /**
     * Validation rules of the filters and the paging; a filter that cannot match anything is an error, not an empty list.
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(): array
    {
        return [
            'texto' => ['sometimes', 'nullable', 'string', 'max:'.Brand::MAX_LENGTH],
            'familia' => ['sometimes', 'nullable', 'string', 'max:'.PerfumeDetails::MAX_FAMILY_LENGTH],
            'nota' => ['sometimes', 'nullable', 'string', 'max:'.PerfumeNote::MAX_LENGTH],
            'momento' => ['sometimes', 'nullable', 'string', Rule::in(array_keys(PerfumeVocabulary::MOMENTS))],
            'clima' => ['sometimes', 'nullable', 'string', Rule::in(array_keys(PerfumeVocabulary::CLIMATES))],
            'ocasion' => ['sometimes', 'nullable', 'string', 'max:'.PerfumeTag::MAX_OCCASION_LENGTH],
            'precio_maximo' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'pagina' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:'.ShowcaseFilters::MAX_PER_PAGE],
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
            'momento.in' => __('catalog.time_of_day_invalid'),
            'clima.in' => __('catalog.climate_invalid'),
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
            'texto' => 'texto',
            'familia' => 'familia olfativa',
            'nota' => 'nota',
            'momento' => 'momento del día',
            'clima' => 'clima',
            'ocasion' => 'ocasión',
            'precio_maximo' => 'precio máximo',
            'pagina' => 'página',
            'por_pagina' => 'resultados por página',
        ];
    }

    /** Map the validated query string to the use case input, with the words of the API changed to the stored values. */
    public function toFilters(): ShowcaseFilters
    {
        $validated = $this->validated();

        return new ShowcaseFilters(
            text: $this->text($validated, 'texto'),
            family: $this->text($validated, 'familia'),
            note: $this->text($validated, 'nota'),
            moment: isset($validated['momento']) ? PerfumeVocabulary::MOMENTS[$validated['momento']] : null,
            climate: isset($validated['clima']) ? PerfumeVocabulary::CLIMATES[$validated['clima']] : null,
            occasion: $this->text($validated, 'ocasion'),
            maxPrice: isset($validated['precio_maximo']) ? (int) $validated['precio_maximo'] : null,
            page: (int) ($validated['pagina'] ?? 1),
            perPage: (int) ($validated['por_pagina'] ?? ShowcaseFilters::DEFAULT_PER_PAGE),
        );
    }

    /**
     * A filter of text, or null when it was not sent or is blank.
     *
     * @param  array<string, mixed>  $validated
     */
    private function text(array $validated, string $key): ?string
    {
        $text = isset($validated[$key]) ? trim((string) $validated[$key]) : '';

        return $text === '' ? null : $text;
    }
}

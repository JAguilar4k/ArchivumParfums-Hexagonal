<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Catalog\Perfumes\Application\DTOs\ListPerfumesQuery;
use Src\Catalog\Perfumes\Domain\ValueObjects\Brand;
use Stringable;

final class IndexPerfumes extends FormRequest
{
    /**
     * Validation rules of the search and the paging of the list.
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(): array
    {
        return [
            'texto' => ['sometimes', 'nullable', 'string', 'max:'.Brand::MAX_LENGTH],
            'pagina' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:'.ListPerfumesQuery::MAX_PER_PAGE],
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
            'pagina' => 'página',
            'por_pagina' => 'resultados por página',
        ];
    }

    /** Map the validated query string to the use case input. */
    public function toQuery(): ListPerfumesQuery
    {
        $validated = $this->validated();

        return new ListPerfumesQuery(
            isset($validated['texto']) ? (string) $validated['texto'] : null,
            (int) ($validated['pagina'] ?? 1),
            (int) ($validated['por_pagina'] ?? ListPerfumesQuery::DEFAULT_PER_PAGE),
        );
    }
}

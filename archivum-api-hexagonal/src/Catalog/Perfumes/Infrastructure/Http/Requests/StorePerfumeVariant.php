<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Catalog\Perfumes\Application\DTOs\AddPerfumeVariantCommand;
use Src\Catalog\Perfumes\Domain\ValueObjects\SizeMl;
use Stringable;

final class StorePerfumeVariant extends FormRequest
{
    /**
     * Validation rules for a new presentation; the milliliters are required and the manual price is optional.
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(): array
    {
        return [
            'ml' => ['required', 'integer', 'min:1', 'max:'.SizeMl::MAX],
            'precio_manual' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Names of the fields in the messages of the errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['ml' => 'ml', 'precio_manual' => 'precio manual'];
    }

    /** Map the validated payload to the use case input. */
    public function toCommand(string $perfumeId): AddPerfumeVariantCommand
    {
        $validated = $this->validated();

        return new AddPerfumeVariantCommand(
            $perfumeId,
            (int) $validated['ml'],
            isset($validated['precio_manual']) ? (int) $validated['precio_manual'] : null,
        );
    }
}

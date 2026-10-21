<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Catalog\Perfumes\Application\DTOs\EditPerfumeVariantCommand;
use Src\Catalog\Perfumes\Domain\ValueObjects\SizeMl;
use Src\Shared\Domain\ValueObjects\Colones;
use Stringable;

final class PatchPerfumeVariant extends FormRequest
{
    /**
     * Validation rules of a partial edit: the milliliters, the manual price (null removes it) and the state.
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(): array
    {
        return [
            'ml' => ['sometimes', 'required', 'integer', 'min:1', 'max:'.SizeMl::MAX],
            'precio_manual' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:'.Colones::MAX],
            'activa' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Names of the fields in the messages of the errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['ml' => 'ml', 'precio_manual' => 'precio manual', 'activa' => 'activa'];
    }

    /** Map the validated payload to the use case input; only what was sent is a change. */
    public function toCommand(string $perfumeId, string $variantId): EditPerfumeVariantCommand
    {
        $validated = $this->validated();
        $changes = [];

        if (isset($validated['ml'])) {
            $changes['ml'] = (int) $validated['ml'];
        }

        if (array_key_exists('precio_manual', $validated)) {
            $changes['manual_price'] = $validated['precio_manual'] === null ? null : (int) $validated['precio_manual'];
        }

        if (isset($validated['activa'])) {
            $changes['active'] = (bool) $validated['activa'];
        }

        return new EditPerfumeVariantCommand($perfumeId, $variantId, $changes);
    }
}

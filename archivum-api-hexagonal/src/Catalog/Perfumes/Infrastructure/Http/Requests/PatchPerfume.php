<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Src\Catalog\Perfumes\Application\DTOs\EditPerfumeCommand;
use Src\Catalog\Perfumes\Infrastructure\Http\PerfumeVocabulary;
use Stringable;

final class PatchPerfume extends FormRequest
{
    /**
     * The same rules as the creation, but every field is optional.
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(): array
    {
        return PerfumeFields::rules(partial: true);
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

    /** Map the validated payload to the use case input; only what was sent is a change, and the business is not part of it. */
    public function toCommand(string $perfumeId): EditPerfumeCommand
    {
        $validated = $this->validated();
        $changes = [];

        if (isset($validated['marca'])) {
            $changes['brand'] = (string) $validated['marca'];
        }

        if (isset($validated['nombre'])) {
            $changes['name'] = (string) $validated['nombre'];
        }

        // The texts that may be missing: sending null clears them.
        if (array_key_exists('concentracion', $validated)) {
            $changes['concentration'] = $this->nullableText($validated['concentracion']);
        }

        if (array_key_exists('genero', $validated)) {
            $changes['gender'] = $this->nullableText($validated['genero']);
        }

        if (array_key_exists('familia', $validated)) {
            $changes['family'] = $this->nullableText($validated['familia']);
        }

        if (array_key_exists('descripcion', $validated)) {
            $changes['description'] = $this->nullableText($validated['descripcion']);
        }

        $notes = array_filter([
            'top' => PerfumeFields::texts($validated, 'notas.salida'),
            'heart' => PerfumeFields::texts($validated, 'notas.corazon'),
            'base' => PerfumeFields::texts($validated, 'notas.fondo'),
        ], fn (?array $texts): bool => $texts !== null);

        $tags = array_filter([
            'time_of_day' => PerfumeFields::texts($validated, 'etiquetas.momento', PerfumeVocabulary::MOMENTS),
            'climate' => PerfumeFields::texts($validated, 'etiquetas.clima', PerfumeVocabulary::CLIMATES),
            'occasion' => PerfumeFields::texts($validated, 'etiquetas.ocasion'),
        ], fn (?array $texts): bool => $texts !== null);

        if ($notes !== []) {
            $changes['notes'] = $notes;
        }

        if ($tags !== []) {
            $changes['tags'] = $tags;
        }

        if (Arr::has($validated, 'activo')) {
            $changes['active'] = (bool) $validated['activo'];
        }

        return new EditPerfumeCommand($perfumeId, $changes);
    }

    /** A text sent, or null when the field was sent empty. */
    private function nullableText(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}

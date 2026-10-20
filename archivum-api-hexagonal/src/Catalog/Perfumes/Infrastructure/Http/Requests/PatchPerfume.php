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
     * Fields of the payload paired with the names the use case expects.
     *
     * @var array<string, string>
     */
    private const array FIELDS = [
        'marca' => 'brand',
        'nombre' => 'name',
        'concentracion' => 'concentration',
        'genero' => 'gender',
        'familia' => 'family',
        'descripcion' => 'description',
    ];

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

        foreach (self::FIELDS as $field => $name) {
            if (array_key_exists($field, $validated)) {
                $changes[$name] = $validated[$field] === null ? null : (string) $validated[$field];
            }
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
}

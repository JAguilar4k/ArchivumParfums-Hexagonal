<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Http\Requests;

use Src\Decants\Packs\Domain\ValueObjects\DecantCount;
use Src\Decants\Packs\Domain\ValueObjects\MlPerDecant;
use Src\Decants\Packs\Domain\ValueObjects\PlanName;
use Src\Decants\Packs\Domain\ValueObjects\ValidityDays;
use Src\Shared\Domain\ValueObjects\Colones;

/** The rules and names of the fields of an offer, shared by the request that defines it and the one that edits it. */
final class DecantPackPlanFields
{
    /**
     * Validation rules with the limits of the domain; when partial, every field is checked only if it is sent. Whether
     * the ml are one of the decant sizes of the store is checked by the use case, which knows the store.
     *
     * @return array<string, list<string>>
     */
    public static function rules(bool $partial): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'nombre' => [...$required, 'string', 'max:'.PlanName::MAX_LENGTH],
            'cantidad_decants' => [...$required, 'integer', 'min:1', 'max:'.DecantCount::MAX],
            'ml_por_decant' => [...$required, 'integer', 'min:1', 'max:'.MlPerDecant::MAX],
            'precio' => [...$required, 'integer', 'min:1', 'max:'.Colones::MAX],
            'vigencia_dias' => [...$required, 'integer', 'min:1', 'max:'.ValidityDays::MAX],
            'precio_maximo_por_ml' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.Colones::MAX],
            'activo' => ['sometimes', 'boolean'],
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
            'nombre' => 'nombre',
            'cantidad_decants' => 'cantidad de decants',
            'ml_por_decant' => 'ml por decant',
            'precio' => 'precio',
            'vigencia_dias' => 'vigencia en días',
            'precio_maximo_por_ml' => 'precio máximo por ml',
            'activo' => 'activo',
        ];
    }
}

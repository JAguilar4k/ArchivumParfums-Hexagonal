<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Infrastructure\Http\Requests;

use Src\Shared\Domain\ValueObjects\Colones;
use Stringable;

/** The fields of a service in the API, shared by the creation and the partial edit. */
final class ServiceFields
{
    /**
     * Rules of the fields; on a partial edit every one of them is optional. The maximum of each integer is the
     * technical limit of the column, not a commercial one.
     *
     * @return array<string, list<Stringable|string>>
     */
    public static function rules(bool $partial): array
    {
        $presence = $partial ? 'sometimes' : 'required';
        $limit = 'max:'.Colones::MAX;

        return [
            'nombre' => [$presence, 'string', 'max:120'],
            'duracion_minutos' => [$presence, 'integer', 'min:1', $limit],
            'precio' => [$presence, 'integer', 'min:0', $limit],
            'margen_minutos' => [$presence, 'integer', 'min:0', $limit],
            'maximo_perfumes' => [$presence, 'integer', 'min:0', $limit],
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
            'duracion_minutos' => 'duración',
            'precio' => 'precio',
            'margen_minutos' => 'margen entre citas',
            'maximo_perfumes' => 'máximo de perfumes',
            'activo' => 'activo',
        ];
    }
}

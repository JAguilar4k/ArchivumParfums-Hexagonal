<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Decants\Packs\Application\DTOs\UpdateDecantPackPlanCommand;

final class PatchDecantPackPlan extends FormRequest
{
    /**
     * The same rules as the definition, but every field is optional.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return DecantPackPlanFields::rules(partial: true);
    }

    /**
     * Names of the fields in the messages of the errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return DecantPackPlanFields::attributes();
    }

    /** Map the validated payload to the use case input; only what was sent is a change, and sending the cap empty removes it. */
    public function toCommand(string $planId): UpdateDecantPackPlanCommand
    {
        $validated = $this->validated();
        $changes = [];

        if (isset($validated['nombre'])) {
            $changes['name'] = (string) $validated['nombre'];
        }

        if (isset($validated['cantidad_decants'])) {
            $changes['decants'] = (int) $validated['cantidad_decants'];
        }

        if (isset($validated['ml_por_decant'])) {
            $changes['ml_per_decant'] = (int) $validated['ml_por_decant'];
        }

        if (isset($validated['precio'])) {
            $changes['price'] = (int) $validated['precio'];
        }

        if (isset($validated['vigencia_dias'])) {
            $changes['validity_days'] = (int) $validated['vigencia_dias'];
        }

        if (array_key_exists('precio_maximo_por_ml', $validated)) {
            $changes['max_price_per_ml'] = $validated['precio_maximo_por_ml'] === null ? null : (int) $validated['precio_maximo_por_ml'];
        }

        if (array_key_exists('activo', $validated)) {
            $changes['active'] = (bool) $validated['activo'];
        }

        return new UpdateDecantPackPlanCommand($planId, $changes);
    }
}

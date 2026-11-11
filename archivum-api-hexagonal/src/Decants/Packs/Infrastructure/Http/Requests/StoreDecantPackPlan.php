<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Decants\Packs\Application\DTOs\CreateDecantPackPlanCommand;

final class StoreDecantPackPlan extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return DecantPackPlanFields::rules(partial: false);
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

    /** Map the validated payload to the use case input; the business is not part of it, it comes from the session. */
    public function toCommand(): CreateDecantPackPlanCommand
    {
        $validated = $this->validated();

        return new CreateDecantPackPlanCommand(
            (string) $validated['nombre'],
            (int) $validated['cantidad_decants'],
            (int) $validated['ml_por_decant'],
            (int) $validated['precio'],
            (int) $validated['vigencia_dias'],
            isset($validated['precio_maximo_por_ml']) ? (int) $validated['precio_maximo_por_ml'] : null,
            (bool) ($validated['activo'] ?? true),
        );
    }
}

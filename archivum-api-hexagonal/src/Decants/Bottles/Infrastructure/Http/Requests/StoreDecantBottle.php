<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Decants\Bottles\Application\DTOs\OpenBottleCommand;
use Stringable;

final class StoreDecantBottle extends FormRequest
{
    /** @return array<string, list<Stringable|string>> */
    public function rules(): array
    {
        return [
            'presentacion_id' => ['bail', 'required', 'uuid'],
            'ml_totales' => ['sometimes', 'integer', 'min:1', 'max:32767'],
            'ml_restantes' => ['sometimes', 'integer', 'min:0', 'max:32767'],
            'abierta_en' => ['required', 'date'],
            'notas' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function toCommand(): OpenBottleCommand
    {
        $data = $this->validated();

        return new OpenBottleCommand((string) $data['presentacion_id'], isset($data['ml_totales']) ? (int) $data['ml_totales'] : null,
            isset($data['ml_restantes']) ? (int) $data['ml_restantes'] : null, (string) $data['abierta_en'], isset($data['notas']) ? (string) $data['notas'] : null);
    }
}

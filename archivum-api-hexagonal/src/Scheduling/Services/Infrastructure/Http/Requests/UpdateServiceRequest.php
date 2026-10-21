<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Infrastructure\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'sometimes|string|max:255',
            'duracion_minutos' => 'sometimes|integer|min:1',
            'precio' => 'sometimes|integer|min:0',
            'margen_minutos' => 'sometimes|integer|min:0',
            'maximo_perfumes' => 'sometimes|integer|min:1',
            'activo' => 'sometimes|boolean',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'error' => 'datos-invalidos',
            'detalles' => $validator->errors(),
        ], 422));
    }
}

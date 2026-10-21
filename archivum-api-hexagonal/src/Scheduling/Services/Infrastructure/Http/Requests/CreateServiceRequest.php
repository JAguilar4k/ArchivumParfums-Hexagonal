<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Infrastructure\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'duracion_minutos' => 'required|integer|min:1',
            'precio' => 'required|integer|min:0',
            'margen_minutos' => 'required|integer|min:0',
            'maximo_perfumes' => 'required|integer|min:1',
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

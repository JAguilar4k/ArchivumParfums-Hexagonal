<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePayment extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['medio' => ['sometimes', 'required', 'string', Rule::in(['tarjeta', 'sinpe_movil'])]];
    }
}

<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AdjustBottle extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['ml' => ['required', 'integer', 'not_in:0', 'min:-2147483647', 'max:2147483647'], 'motivo' => ['required', 'string']];
    }
}

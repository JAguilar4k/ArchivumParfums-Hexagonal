<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PatchDecantBottle extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['notas' => ['sometimes', 'nullable', 'string']];
    }
}

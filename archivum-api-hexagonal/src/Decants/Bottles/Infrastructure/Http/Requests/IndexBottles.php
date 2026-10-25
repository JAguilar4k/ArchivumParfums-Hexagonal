<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class IndexBottles extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['pagina' => ['sometimes', 'integer', 'min:1'], 'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }
}

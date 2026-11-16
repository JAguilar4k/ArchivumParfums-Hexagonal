<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;

final class IndexSoldPacks extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['pagina' => ['sometimes', 'integer', 'min:1'], 'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'estado' => ['sometimes', 'string', Rule::in(array_map(fn (DecantPackStatus $status): string => $status->spanish(), DecantPackStatus::cases()))]];
    }
}

<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Sales\Orders\Domain\ValueObjects\OrderStatus;

final class IndexOrders extends FormRequest
{
    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        return ['pagina' => ['sometimes', 'integer', 'min:1'], 'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'estado' => ['sometimes', 'string', Rule::in(array_map(fn (OrderStatus $status): string => $status->spanish(), OrderStatus::cases()))]];
    }
}

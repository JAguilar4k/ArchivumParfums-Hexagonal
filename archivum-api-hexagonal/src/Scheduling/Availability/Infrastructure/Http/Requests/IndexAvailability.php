<?php

declare(strict_types=1);

namespace Src\Scheduling\Availability\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class IndexAvailability extends FormRequest
{
    /**
     * The query string of the public availability: the service to book, the local day and, when the visitor wants a
     * person, that person.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'servicio' => ['required', 'uuid'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'persona' => ['sometimes', 'nullable', 'uuid'],
        ];
    }

    /**
     * Names of the query parameters in the messages of the errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'servicio' => 'servicio',
            'fecha' => 'fecha',
            'persona' => 'persona',
        ];
    }
}

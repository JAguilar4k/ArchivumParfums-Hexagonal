<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Scheduling\Appointments\Domain\ValueObjects\AppointmentPeriod;

final class IndexAppointments extends FormRequest
{
    /**
     * The filters of the listing of the customer: the period and the page.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'periodo' => ['sometimes', 'nullable', Rule::in(array_column(AppointmentPeriod::cases(), 'value'))],
            'pagina' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * Names of the query parameters in the messages of the errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['periodo' => 'periodo', 'pagina' => 'página', 'por_pagina' => 'citas por página'];
    }

    /** The period the customer asked for; the upcoming appointments are the default. */
    public function period(): AppointmentPeriod
    {
        $period = $this->query('periodo');

        return is_string($period) ? (AppointmentPeriod::tryFrom($period) ?? AppointmentPeriod::Upcoming) : AppointmentPeriod::Upcoming;
    }
}

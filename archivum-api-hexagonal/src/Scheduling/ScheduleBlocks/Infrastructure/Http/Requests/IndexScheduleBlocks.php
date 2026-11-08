<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Infrastructure\Http\Requests;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Src\Scheduling\ScheduleBlocks\Application\DTOs\ScheduleBlockFilters;
use Stringable;

final class IndexScheduleBlocks extends FormRequest
{
    /**
     * Rules of the range the panel asks for; the person is an optional filter and the business comes from the session.
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(): array
    {
        return [
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after:desde'],
            'persona' => ['sometimes', 'nullable', 'uuid'],
        ];
    }

    /**
     * Names of the fields in the messages of the errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['desde' => 'desde', 'hasta' => 'hasta', 'persona' => 'persona'];
    }

    /** Map the validated payload to the use case input. */
    public function toFilters(): ScheduleBlockFilters
    {
        $validated = $this->validated();
        $person = $validated['persona'] ?? null;

        return new ScheduleBlockFilters(
            new DateTimeImmutable((string) $validated['desde']),
            new DateTimeImmutable((string) $validated['hasta']),
            is_string($person) && $person !== '' ? $person : null,
        );
    }
}

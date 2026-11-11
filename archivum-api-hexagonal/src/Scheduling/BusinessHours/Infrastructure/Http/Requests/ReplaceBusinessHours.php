<?php

declare(strict_types=1);

namespace Src\Scheduling\BusinessHours\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Src\Scheduling\BusinessHours\Application\DTOs\BusinessHoursSlot;
use Src\Scheduling\BusinessHours\Application\DTOs\ReplaceBusinessHoursCommand;
use Stringable;

final class ReplaceBusinessHours extends FormRequest
{
    /**
     * Rules of the whole schedule; the business is never read from the body, it comes from the session.
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(): array
    {
        return [
            'tramos' => ['present', 'array'],
            'tramos.*.dia_semana' => ['required', 'integer', 'between:1,7'],
            'tramos.*.inicio' => ['required', 'date_format:H:i'],
            'tramos.*.fin' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * Names of the fields in the messages of the errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tramos' => 'tramos',
            'tramos.*.dia_semana' => 'día de la semana',
            'tramos.*.inicio' => 'hora de inicio',
            'tramos.*.fin' => 'hora de fin',
        ];
    }

    /**
     * Refuse a tramo that ends before it starts and two tramos that overlap on the same day, pointing at the tramo.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $tramos = $this->input('tramos');

            if (! is_array($tramos)) {
                return;
            }

            /** @var array<int, list<array{string, string}>> $byWeekday */
            $byWeekday = [];

            foreach ($tramos as $index => $tramo) {
                if (! is_array($tramo) || ! isset($tramo['dia_semana'], $tramo['inicio'], $tramo['fin'])) {
                    continue;
                }

                $weekday = (int) $tramo['dia_semana'];
                $opens = (string) $tramo['inicio'];
                $closes = (string) $tramo['fin'];

                if ($closes <= $opens) {
                    $validator->errors()->add("tramos.{$index}.fin", __('scheduling.business_hours.invalid_order'));

                    continue;
                }

                foreach ($byWeekday[$weekday] ?? [] as [$otherOpens, $otherCloses]) {
                    if ($opens < $otherCloses && $closes > $otherOpens) {
                        $validator->errors()->add("tramos.{$index}.inicio", __('scheduling.business_hours.overlaps'));

                        continue 2;
                    }
                }

                $byWeekday[$weekday][] = [$opens, $closes];
            }
        }];
    }

    /** Map the validated payload to the use case input. */
    public function toCommand(): ReplaceBusinessHoursCommand
    {
        $tramos = $this->validated()['tramos'] ?? [];
        $slots = [];

        foreach (is_array($tramos) ? $tramos : [] as $tramo) {
            $slots[] = new BusinessHoursSlot(
                (int) $tramo['dia_semana'],
                (string) $tramo['inicio'],
                (string) $tramo['fin'],
            );
        }

        return new ReplaceBusinessHoursCommand($slots);
    }
}

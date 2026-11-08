<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Infrastructure\Http\Requests;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Src\Scheduling\ScheduleBlocks\Application\DTOs\CreateScheduleBlockCommand;
use Stringable;

final class StoreScheduleBlock extends FormRequest
{
    /**
     * Rules for a new block; the business is never read from the body, it comes from the session.
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(): array
    {
        return ScheduleBlockFields::rules(partial: false);
    }

    /**
     * Names of the fields in the messages of the errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ScheduleBlockFields::attributes();
    }

    /**
     * Refuse a person who is not staff of this business, with the error on her field as #57 asks.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $member = $this->input('persona_id');

            if (is_string($member) && $member !== '' && ! ScheduleBlockFields::memberBelongsToTheBusiness($member)) {
                $validator->errors()->add('persona_id', __('scheduling.schedule_block.member_not_found'));
            }
        }];
    }

    /** Map the validated payload to the use case input; the instants keep their offset. */
    public function toCommand(): CreateScheduleBlockCommand
    {
        $validated = $this->validated();
        $member = $validated['persona_id'] ?? null;

        return new CreateScheduleBlockCommand(
            memberId: is_string($member) && $member !== '' ? $member : null,
            startsAt: new DateTimeImmutable((string) $validated['inicia_en']),
            endsAt: new DateTimeImmutable((string) $validated['termina_en']),
            reason: (string) $validated['motivo'],
        );
    }
}

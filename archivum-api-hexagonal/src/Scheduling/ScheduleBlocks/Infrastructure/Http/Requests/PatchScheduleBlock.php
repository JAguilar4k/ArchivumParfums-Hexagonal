<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Infrastructure\Http\Requests;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Src\Scheduling\ScheduleBlocks\Application\DTOs\EditScheduleBlockCommand;
use Stringable;

final class PatchScheduleBlock extends FormRequest
{
    /**
     * The same rules as the creation, but every field is optional.
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(): array
    {
        return ScheduleBlockFields::rules(partial: true);
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

    /** Map the validated payload to the use case input; only what was sent is a change, and the business is not part of it. */
    public function toCommand(string $blockId): EditScheduleBlockCommand
    {
        $validated = $this->validated();
        $changes = [];

        if (array_key_exists('persona_id', $validated)) {
            $member = $validated['persona_id'];
            $changes['member_id'] = is_string($member) && $member !== '' ? $member : null;
        }

        if (isset($validated['inicia_en'])) {
            $changes['starts_at'] = new DateTimeImmutable((string) $validated['inicia_en']);
        }

        if (isset($validated['termina_en'])) {
            $changes['ends_at'] = new DateTimeImmutable((string) $validated['termina_en']);
        }

        if (isset($validated['motivo'])) {
            $changes['reason'] = (string) $validated['motivo'];
        }

        return new EditScheduleBlockCommand($blockId, $changes);
    }
}

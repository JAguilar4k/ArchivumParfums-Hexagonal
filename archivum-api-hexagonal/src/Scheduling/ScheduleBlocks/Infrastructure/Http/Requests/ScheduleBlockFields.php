<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Infrastructure\Http\Requests;

use Src\Scheduling\ScheduleBlocks\Domain\Contracts\ScheduleBlockRepository;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\MemberId;
use Stringable;

/** The fields of a block in the API, shared by the creation and the partial edit. */
final class ScheduleBlockFields
{
    /**
     * Whether that person belongs to the staff of the business of the session, so the error lands on her field; an
     * identifier that is not a person is simply not one of this business.
     */
    public static function memberBelongsToTheBusiness(string $memberId): bool
    {
        try {
            /** @var ScheduleBlockRepository $blocks */
            $blocks = app(ScheduleBlockRepository::class);

            return $blocks->memberExists(new MemberId($memberId));
        } catch (InvalidValueException) {
            return false;
        }
    }

    /**
     * Rules of the fields; on a partial edit every one of them is optional. The person is nullable: without her the block
     * covers the whole store. The instants travel in ISO 8601 with offset.
     *
     * @return array<string, list<Stringable|string>>
     */
    public static function rules(bool $partial): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return [
            'persona_id' => ['sometimes', 'nullable', 'uuid'],
            'inicia_en' => [$presence, 'date'],
            'termina_en' => [$presence, 'date', 'after:inicia_en'],
            'motivo' => [$presence, 'string', 'max:255'],
        ];
    }

    /**
     * Names of the fields in the messages of the errors.
     *
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'persona_id' => 'persona',
            'inicia_en' => 'inicio del bloqueo',
            'termina_en' => 'fin del bloqueo',
            'motivo' => 'motivo',
        ];
    }
}

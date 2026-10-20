<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class PerfumeTag
{
    public const int MAX_OCCASION_LENGTH = 60;

    private function __construct(public TagKind $kind, public string $value) {}

    /** Tag the moment of the day the perfume suits. */
    public static function timeOfDay(TimeOfDay $timeOfDay): self
    {
        return new self(TagKind::TimeOfDay, $timeOfDay->value);
    }

    /** Tag the climate the perfume suits. */
    public static function climate(Climate $climate): self
    {
        return new self(TagKind::Climate, $climate->value);
    }

    /** Tag a free-text occasion, trimmed and rejected when empty or oversized. */
    public static function occasion(string $occasion): self
    {
        $occasion = trim($occasion);

        if ($occasion === '' || mb_strlen($occasion) > self::MAX_OCCASION_LENGTH) {
            throw new InvalidValueException('catalog.occasion_invalid', ['max' => self::MAX_OCCASION_LENGTH]);
        }

        return new self(TagKind::Occasion, $occasion);
    }

    /** Build a tag from its stored kind and value, rejecting a moment or climate that does not exist. */
    public static function fromStored(string $kind, string $value): self
    {
        return match (TagKind::tryFrom($kind) ?? throw new InvalidValueException('catalog.tag_kind_invalid')) {
            TagKind::TimeOfDay => self::timeOfDay(
                TimeOfDay::tryFrom($value) ?? throw new InvalidValueException('catalog.time_of_day_invalid'),
            ),
            TagKind::Climate => self::climate(
                Climate::tryFrom($value) ?? throw new InvalidValueException('catalog.climate_invalid'),
            ),
            TagKind::Occasion => self::occasion($value),
        };
    }

    /** Compare two tags by kind and value, ignoring case. */
    public function equals(self $other): bool
    {
        return $other->kind === $this->kind && mb_strtolower($other->value) === mb_strtolower($this->value);
    }
}

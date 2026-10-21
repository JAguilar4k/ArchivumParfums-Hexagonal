<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

/** The optional descriptive data of a perfume; a blank text counts as missing. */
final readonly class PerfumeDetails
{
    public const int MAX_CONCENTRATION_LENGTH = 60;

    public const int MAX_GENDER_LENGTH = 30;

    public const int MAX_FAMILY_LENGTH = 60;

    public const int MAX_DESCRIPTION_LENGTH = 1000;

    public ?string $concentration;

    public ?string $gender;

    public ?string $family;

    public ?string $description;

    /** Trim each text and reject the ones that exceed the column that stores them. */
    public function __construct(?string $concentration, ?string $gender, ?string $family, ?string $description)
    {
        $this->concentration = self::normalize($concentration, 'catalog.concentration_too_long', self::MAX_CONCENTRATION_LENGTH);
        $this->gender = self::normalize($gender, 'catalog.gender_too_long', self::MAX_GENDER_LENGTH);
        $this->family = self::normalize($family, 'catalog.family_too_long', self::MAX_FAMILY_LENGTH);
        $this->description = self::normalize($description, 'catalog.description_too_long', self::MAX_DESCRIPTION_LENGTH);
    }

    /** Build details with no optional data. */
    public static function empty(): self
    {
        return new self(null, null, null, null);
    }

    private static function normalize(?string $text, string $translationKey, int $max): ?string
    {
        $text = trim($text ?? '');

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) > $max) {
            throw new InvalidValueException($translationKey, ['max' => $max]);
        }

        return $text;
    }
}

<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class BusinessSettings
{
    public const int DEFAULT_INTERVIEW_MARGIN_COLONES = 5000;

    /** @var list<int> */
    public const array DEFAULT_DECANT_SIZES_ML = [2, 5, 10];

    public const int DEFAULT_BOTTLE_DEPOSIT_PERCENT = 50;

    public const int DEFAULT_DECANT_ROUNDING_COLONES = 100;

    public const int DEFAULT_TRIAL_APPOINTMENT_PRICE_COLONES = 0;

    /**
     * @param  list<int>  $decantSizesMl
     */
    public function __construct(
        public int $interviewMarginColones = self::DEFAULT_INTERVIEW_MARGIN_COLONES,
        public array $decantSizesMl = self::DEFAULT_DECANT_SIZES_ML,
        public int $bottleDepositPercent = self::DEFAULT_BOTTLE_DEPOSIT_PERCENT,
        public int $decantRoundingColones = self::DEFAULT_DECANT_ROUNDING_COLONES,
        public int $trialAppointmentPriceColones = self::DEFAULT_TRIAL_APPOINTMENT_PRICE_COLONES,
    ) {
        if ($interviewMarginColones < 0) {
            throw new InvalidValueException('businesses.margin_negative');
        }

        if ($decantSizesMl === []) {
            throw new InvalidValueException('businesses.decant_sizes_invalid');
        }

        foreach ($decantSizesMl as $size) {
            if ($size <= 0) {
                throw new InvalidValueException('businesses.decant_sizes_invalid');
            }
        }

        if ($bottleDepositPercent < 0 || $bottleDepositPercent > 100) {
            throw new InvalidValueException('businesses.deposit_percent_invalid');
        }

        if ($decantRoundingColones <= 0) {
            throw new InvalidValueException('businesses.rounding_invalid');
        }

        if ($trialAppointmentPriceColones < 0) {
            throw new InvalidValueException('businesses.trial_price_negative');
        }
    }

    /** Settings that every new business starts with. */
    public static function defaults(): self
    {
        return new self;
    }
}

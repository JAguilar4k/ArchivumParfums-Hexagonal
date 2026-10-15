<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class BusinessSettings
{
    public const int DEFAULT_BOTTLE_MARGIN_COLONES = 5000;

    /** @var list<int> */
    public const array DEFAULT_DECANT_SIZES_ML = [2, 5, 10];

    public const int DEFAULT_BOTTLE_DEPOSIT_PERCENT = 50;

    public const int DEFAULT_DECANT_ROUNDING_COLONES = 100;

    public const int DEFAULT_TRIAL_APPOINTMENT_PRICE_COLONES = 0;

    public const int DEFAULT_MINIMUM_NOTICE_HOURS = 12;

    public const int DEFAULT_BOOKING_HORIZON_DAYS = 30;

    public const int DEFAULT_APPOINTMENT_CHANGE_CUTOFF_HOURS = 2;

    public const int DEFAULT_WAITLIST_HOLD_HOURS = 2;

    public const int DEFAULT_UNPAID_EXPIRY_MINUTES = 30;

    /** @var list<int> */
    public const array DEFAULT_REMINDER_HOURS = [24, 2];

    public const string GATEWAY_ONVO = 'onvo';

    public const string GATEWAY_PAYPAL = 'paypal';

    /** @var list<string> */
    public const array GATEWAYS = [self::GATEWAY_ONVO, self::GATEWAY_PAYPAL];

    /**
     * Hold the settings of a business; the defaults are the ones of section 10 of the specification.
     *
     * @param  list<int>  $decantSizesMl
     * @param  list<int>  $reminderHours  Hours before an appointment when each reminder goes out.
     * @param  float|null  $usdExchangeRate  Colones per dollar for PayPal; null until the business sets it.
     */
    public function __construct(
        public int $bottleMarginColones = self::DEFAULT_BOTTLE_MARGIN_COLONES,
        public array $decantSizesMl = self::DEFAULT_DECANT_SIZES_ML,
        public int $bottleDepositPercent = self::DEFAULT_BOTTLE_DEPOSIT_PERCENT,
        public int $decantRoundingColones = self::DEFAULT_DECANT_ROUNDING_COLONES,
        public int $trialAppointmentPriceColones = self::DEFAULT_TRIAL_APPOINTMENT_PRICE_COLONES,
        public int $minimumNoticeHours = self::DEFAULT_MINIMUM_NOTICE_HOURS,
        public int $bookingHorizonDays = self::DEFAULT_BOOKING_HORIZON_DAYS,
        public int $appointmentChangeCutoffHours = self::DEFAULT_APPOINTMENT_CHANGE_CUTOFF_HOURS,
        public int $waitlistHoldHours = self::DEFAULT_WAITLIST_HOLD_HOURS,
        public int $unpaidExpiryMinutes = self::DEFAULT_UNPAID_EXPIRY_MINUTES,
        public array $reminderHours = self::DEFAULT_REMINDER_HOURS,
        public string $defaultGateway = self::GATEWAY_ONVO,
        public ?float $usdExchangeRate = null,
    ) {
        if ($bottleMarginColones < 0) {
            throw new InvalidValueException('businesses.margin_negative');
        }

        if ($decantSizesMl === [] || count(array_unique($decantSizesMl)) !== count($decantSizesMl)) {
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

        foreach ([$minimumNoticeHours, $bookingHorizonDays, $appointmentChangeCutoffHours, $waitlistHoldHours, $unpaidExpiryMinutes, ...$reminderHours] as $term) {
            if ($term < 0) {
                throw new InvalidValueException('businesses.term_negative');
            }
        }

        if ($bookingHorizonDays * 24 < $minimumNoticeHours) {
            throw new InvalidValueException('businesses.horizon_before_notice');
        }

        if (! in_array($defaultGateway, self::GATEWAYS, true)) {
            throw new InvalidValueException('businesses.gateway_invalid');
        }

        if ($usdExchangeRate !== null && $usdExchangeRate <= 0) {
            throw new InvalidValueException('businesses.exchange_rate_invalid');
        }
    }

    /** Settings that every new business starts with. */
    public static function defaults(): self
    {
        return new self;
    }

    /**
     * Combine these settings with the changed ones, by property name, and validate the result as a whole.
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return new self(...[...get_object_vars($this), ...$changes]);
    }
}

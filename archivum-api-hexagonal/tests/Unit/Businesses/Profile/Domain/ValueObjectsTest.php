<?php

declare(strict_types=1);

use Src\Businesses\Profile\Domain\ValueObjects\AppointmentPlace;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessName;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSettings;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSlug;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessTimezone;
use Src\Shared\Domain\Exceptions\InvalidValueException;

it('trims the business name', function (): void {
    expect((new BusinessName('  Archivum  '))->value)->toBe('Archivum');
});

it('rejects an empty business name', function (): void {
    new BusinessName('   ');
})->throws(InvalidValueException::class, 'businesses.name_invalid');

it('rejects a business name that is too long', function (): void {
    new BusinessName(str_repeat('a', BusinessName::MAX_LENGTH + 1));
})->throws(InvalidValueException::class, 'businesses.name_invalid');

it('accepts a valid slug', function (): void {
    expect((new BusinessSlug('mi-tienda-2'))->value)->toBe('mi-tienda-2');
});

it('rejects invalid slugs', function (string $slug): void {
    new BusinessSlug($slug);
})->with([
    'uppercase letters' => 'Mi-Tienda',
    'spaces' => 'mi tienda',
    'underscores' => 'mi_tienda',
    'special characters' => 'tienda!',
    'empty slug' => '',
])->throws(InvalidValueException::class, 'businesses.slug_invalid');

it('accepts an IANA timezone', function (): void {
    expect((new BusinessTimezone('America/Costa_Rica'))->value)
        ->toBe('America/Costa_Rica');
});

it('rejects a timezone that is not IANA', function (string $timezone): void {
    new BusinessTimezone($timezone);
})->with([
    'timezone with spaces' => 'Costa Rica',
    'invalid casing' => 'america/costa_rica',
    'unknown timezone' => 'Mars/Olympus',
    'empty timezone' => '',
])->throws(InvalidValueException::class, 'businesses.timezone_invalid');

it('rejects an empty appointment place', function () {
    new AppointmentPlace('  ');
})->throws(InvalidValueException::class, 'businesses.appointment_place_invalid');

it('rejects an appointment place that is too long', function (): void {
    new AppointmentPlace(str_repeat('a', AppointmentPlace::MAX_LENGTH + 1));
})->throws(InvalidValueException::class, 'businesses.appointment_place_invalid');

it('starts settings with the specification defaults', function (): void {
    $settings = BusinessSettings::defaults();

    expect($settings->bottleMarginColones)->toBe(5000)
        ->and($settings->decantSizesMl)->toBe([2, 5, 10]);
});

it('rejects a negative margin', function (): void {
    new BusinessSettings(bottleMarginColones: -1);
})->throws(InvalidValueException::class, 'businesses.margin_negative');

it('rejects empty decant sizes', function (): void {
    new BusinessSettings(decantSizesMl: []);
})->throws(InvalidValueException::class, 'businesses.decant_sizes_invalid');

it('rejects a zero decant size', function (): void {
    new BusinessSettings(decantSizesMl: [0]);
})->throws(InvalidValueException::class, 'businesses.decant_sizes_invalid');

it('rejects a negative decant size', function (): void {
    new BusinessSettings(decantSizesMl: [2, -5]);
})->throws(InvalidValueException::class, 'businesses.decant_sizes_invalid');

it('starts settings with the deposit, rounding and free trial appointment defaults', function (): void {
    $settings = BusinessSettings::defaults();

    expect($settings->bottleDepositPercent)->toBe(50)
        ->and($settings->decantRoundingColones)->toBe(100)
        ->and($settings->trialAppointmentPriceColones)->toBe(0);
});

it('rejects a deposit percent outside 0 to 100', function (int $percent): void {
    new BusinessSettings(bottleDepositPercent: $percent);
})->with([-1, 101])
    ->throws(InvalidValueException::class, 'businesses.deposit_percent_out_of_range');

it('rejects a decant rounding that is not positive', function (int $rounding): void {
    new BusinessSettings(decantRoundingColones: $rounding);
})->with([0, -100])
    ->throws(InvalidValueException::class, 'businesses.rounding_invalid');

it('rejects a negative trial appointment price', function (): void {
    new BusinessSettings(trialAppointmentPriceColones: -1);
})->throws(InvalidValueException::class, 'businesses.trial_price_negative');

it('rejects repeated decant sizes', function (): void {
    new BusinessSettings(decantSizesMl: [5, 5]);
})->throws(InvalidValueException::class, 'businesses.decant_sizes_invalid');

it('starts settings with the booking, waitlist, payment and reminder defaults', function (): void {
    $settings = BusinessSettings::defaults();

    expect($settings->minimumNoticeHours)->toBe(12)
        ->and($settings->bookingHorizonDays)->toBe(30)
        ->and($settings->appointmentChangeCutoffHours)->toBe(2)
        ->and($settings->waitlistHoldHours)->toBe(2)
        ->and($settings->unpaidExpiryMinutes)->toBe(30)
        ->and($settings->reminderHours)->toBe([24, 2])
        ->and($settings->defaultGateway)->toBe('onvo')
        ->and($settings->usdExchangeRate)->toBeNull();
});

it('rejects a negative term', function (string $setting): void {
    new BusinessSettings(...[$setting => -1]);
})->with([
    'minimumNoticeHours',
    'bookingHorizonDays',
    'appointmentChangeCutoffHours',
    'waitlistHoldHours',
    'unpaidExpiryMinutes',
])->throws(InvalidValueException::class, 'businesses.term_negative');

it('rejects a negative reminder', function (): void {
    new BusinessSettings(reminderHours: [24, -2]);
})->throws(InvalidValueException::class, 'businesses.term_negative');

it('rejects a booking horizon shorter than the minimum notice', function (): void {
    new BusinessSettings(minimumNoticeHours: 49, bookingHorizonDays: 2);
})->throws(InvalidValueException::class, 'businesses.horizon_before_notice');

it('accepts a booking horizon as long as the minimum notice', function (): void {
    expect((new BusinessSettings(minimumNoticeHours: 48, bookingHorizonDays: 2))->bookingHorizonDays)->toBe(2);
});

it('rejects a gateway other than onvo or paypal', function (): void {
    new BusinessSettings(defaultGateway: 'stripe');
})->throws(InvalidValueException::class, 'businesses.gateway_invalid');

it('rejects an exchange rate that is not positive', function (float $rate): void {
    new BusinessSettings(usdExchangeRate: $rate);
})->with([0.0, -505.5])->throws(InvalidValueException::class, 'businesses.exchange_rate_invalid');

it('combines the changed settings with the current ones and validates the result', function (): void {
    $settings = (new BusinessSettings(bottleMarginColones: 8000))->with(['minimumNoticeHours' => 24, 'defaultGateway' => 'paypal']);

    expect($settings->bottleMarginColones)->toBe(8000)
        ->and($settings->minimumNoticeHours)->toBe(24)
        ->and($settings->defaultGateway)->toBe('paypal')
        ->and($settings->bookingHorizonDays)->toBe(30);
});

it('validates the combination, not only the changed setting', function (): void {
    (new BusinessSettings(bookingHorizonDays: 1))->with(['minimumNoticeHours' => 25]);
})->throws(InvalidValueException::class, 'businesses.horizon_before_notice');

it('rejects decant sizes that are not a list of positive integers', function (array $sizes): void {
    new BusinessSettings(decantSizesMl: $sizes);
})->with([
    'text' => [['texto']],
    'numeric string' => [['5']],
    'boolean' => [[true]],
    'fractional float' => [[2.5]],
    'whole float' => [[5.0]],
    'associative array' => [['small' => 2]],
    'nonzero first index' => [[1 => 5]],
    'nested array' => [[[5]]],
])->throws(InvalidValueException::class, 'businesses.decant_sizes_invalid');

it('rejects a slug ending in a line break', function (string $slug) {
    new BusinessSlug($slug);
})->with(["archivum\n", "archivum\r", "archivum\r\n"])->throws(InvalidValueException::class, 'businesses.slug_invalid');

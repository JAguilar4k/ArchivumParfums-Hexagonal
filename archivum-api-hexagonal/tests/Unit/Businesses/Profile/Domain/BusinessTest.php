<?php

declare(strict_types=1);

use Src\Businesses\Profile\Domain\Entities\Business;
use Src\Businesses\Profile\Domain\ValueObjects\AppointmentPlace;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessName;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSettings;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSlug;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessTimezone;
use Src\Shared\Domain\ValueObjects\BusinessId;

const BUSINESS_UUID = '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6b';

it('creates a business with the default settings', function (): void {
    $business = Business::create(
        new BusinessId(BUSINESS_UUID),
        new BusinessName('Archivum'),
        new BusinessSlug('archivum'),
        new BusinessTimezone('America/Costa_Rica'),
        new AppointmentPlace('Escazú, frente al parque'),
    );

    expect($business->id->value)->toBe(BUSINESS_UUID)
        ->and($business->name->value)->toBe('Archivum')
        ->and($business->settings->bottleMarginColones)->toBe(5000)
        ->and($business->settings->decantSizesMl)->toBe([2, 5, 10]);
});

it('reconstitutes a business keeping its stored settings', function (): void {
    $business = Business::reconstitute(
        new BusinessId(BUSINESS_UUID),
        new BusinessName('Archivum'),
        new BusinessSlug('archivum'),
        new BusinessTimezone('America/Costa_Rica'),
        null,
        new BusinessSettings(bottleMarginColones: 8000, decantSizesMl: [3]),
    );

    expect($business->settings->bottleMarginColones)->toBe(8000)
        ->and($business->settings->decantSizesMl)->toBe([3]);
});

it('cannot be built with new because the constructor is private', function (): void {
    expect((new ReflectionClass(Business::class))->getConstructor()?->isPrivate())->toBeTrue();
});

it('revises the profile and the settings but keeps its identity and its slug', function (): void {
    $business = Business::create(
        new BusinessId(BUSINESS_UUID),
        new BusinessName('Archivum'),
        new BusinessSlug('archivum'),
        new BusinessTimezone('America/Costa_Rica'),
        null,
    );

    $business->revise(
        new BusinessName('Archivum Parfums'),
        new BusinessTimezone('America/Panama'),
        new AppointmentPlace('Café del parque central'),
        $business->settings->with(['bottleMarginColones' => 6000]),
    );

    expect($business->id->value)->toBe(BUSINESS_UUID)
        ->and($business->slug->value)->toBe('archivum')
        ->and($business->name->value)->toBe('Archivum Parfums')
        ->and($business->timezone->value)->toBe('America/Panama')
        ->and($business->appointmentPlace?->value)->toBe('Café del parque central')
        ->and($business->settings->bottleMarginColones)->toBe(6000)
        ->and($business->settings->decantSizesMl)->toBe([2, 5, 10]);
});

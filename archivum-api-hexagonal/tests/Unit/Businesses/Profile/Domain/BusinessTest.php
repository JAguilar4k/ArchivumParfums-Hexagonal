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
        ->and($business->appointmentPlace?->value)->toBe('Escazú, frente al parque')
        ->and($business->settings->interviewMarginColones)->toBe(5000)
        ->and($business->settings->decantSizesMl)->toBe([2, 5, 10]);
});

it('reconstitutes a business keeping its stored settings', function (): void {
    $business = Business::reconstitute(
        new BusinessId(BUSINESS_UUID),
        new BusinessName('Archivum'),
        new BusinessSlug('archivum'),
        new BusinessTimezone('America/Costa_Rica'),
        null,
        new BusinessSettings(interviewMarginColones: 8000, decantSizesMl: [3]),
    );

    expect($business->appointmentPlace)->toBeNull()
        ->and($business->settings->interviewMarginColones)->toBe(8000)
        ->and($business->settings->decantSizesMl)->toBe([3]);
});

it('cannot be built with new because the constructor is private', function (): void {
    expect((new ReflectionClass(Business::class))->getConstructor()?->isPrivate())->toBeTrue();
});

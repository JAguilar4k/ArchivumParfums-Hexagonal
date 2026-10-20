<?php

declare(strict_types=1);

use Src\Businesses\Profile\Application\DTOs\UpdateBusinessProfileCommand;
use Src\Businesses\Profile\Application\UseCases\UpdateBusinessProfile;
use Src\Businesses\Profile\Domain\Entities\Business;
use Src\Businesses\Profile\Domain\Exceptions\BusinessNotFoundException;
use Src\Businesses\Profile\Domain\ValueObjects\AppointmentPlace;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessName;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSettings;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSlug;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessTimezone;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Tests\Support\Businesses\InMemoryBusinessRepository;
use Tests\Support\Shared\RecordingTransactionRunner;

/**
 * Wire the use case to an in-memory repository with a business whose margin, deposit and trial price are already changed.
 *
 * @return array{UpdateBusinessProfile, Business, InMemoryBusinessRepository, RecordingTransactionRunner}
 */
function updateBusinessProfileWiring(): array
{
    $businesses = new InMemoryBusinessRepository;
    $business = Business::reconstitute(
        $businesses->nextId(),
        new BusinessName('ArchivumParfums'),
        new BusinessSlug('archivumparfums'),
        new BusinessTimezone('America/Costa_Rica'),
        new AppointmentPlace('Escazú, frente al parque'),
        new BusinessSettings(bottleMarginColones: 7000, bottleDepositPercent: 40, trialAppointmentPriceColones: 3000),
    );
    $businesses->save($business);

    $transaction = new RecordingTransactionRunner;

    return [new UpdateBusinessProfile($businesses, $transaction), $business, $businesses, $transaction];
}

it('changes only the fields given and keeps the slug', function () {
    [$updateProfile, $business, $businesses] = updateBusinessProfileWiring();

    $updated = $updateProfile->execute(new UpdateBusinessProfileCommand($business->id->value, [
        'name' => 'Archivum Parfums',
        'appointment_place' => 'Café del parque central',
    ]));

    expect($updated->name->value)->toBe('Archivum Parfums')
        ->and($updated->appointmentPlace?->value)->toBe('Café del parque central')
        ->and($updated->timezone->value)->toBe('America/Costa_Rica')
        ->and($updated->slug->value)->toBe('archivumparfums')
        ->and($updated->settings->bottleMarginColones)->toBe(7000)
        ->and($businesses->find($business->id))->toBe($updated);
});

it('combines the settings given with the stored ones', function () {
    [$updateProfile, $business] = updateBusinessProfileWiring();

    $updated = $updateProfile->execute(new UpdateBusinessProfileCommand($business->id->value, [
        'timezone' => 'America/Panama',
        'settings' => ['decant_sizes_ml' => [3, 8], 'default_gateway' => 'paypal', 'usd_exchange_rate' => 512.5],
    ]));

    expect($updated->timezone->value)->toBe('America/Panama')
        ->and($updated->settings->bottleMarginColones)->toBe(7000)
        ->and($updated->settings->decantSizesMl)->toBe([3, 8])
        ->and($updated->settings->defaultGateway)->toBe('paypal')
        ->and($updated->settings->usdExchangeRate)->toBe(512.5)
        ->and($updated->settings->minimumNoticeHours)->toBe(12);
});

it('keeps the stored settings that the profile does not expose', function () {
    [$updateProfile, $business] = updateBusinessProfileWiring();

    $updated = $updateProfile->execute(new UpdateBusinessProfileCommand($business->id->value, [
        'settings' => ['bottle_margin_colones' => 6000],
    ]));

    expect($updated->settings->bottleMarginColones)->toBe(6000)
        ->and($updated->settings->bottleDepositPercent)->toBe(40)
        ->and($updated->settings->decantRoundingColones)->toBe(BusinessSettings::DEFAULT_DECANT_ROUNDING_COLONES)
        ->and($updated->settings->trialAppointmentPriceColones)->toBe(3000);
});

it('clears the exchange rate with null', function () {
    [$updateProfile, $business] = updateBusinessProfileWiring();
    $updateProfile->execute(new UpdateBusinessProfileCommand($business->id->value, ['settings' => ['usd_exchange_rate' => 512.5]]));

    $updated = $updateProfile->execute(new UpdateBusinessProfileCommand($business->id->value, ['settings' => ['usd_exchange_rate' => null]]));

    expect($updated->settings->usdExchangeRate)->toBeNull();
});

it('validates the horizon against the stored notice when only one of them changes', function () {
    [$updateProfile, $business, $businesses] = updateBusinessProfileWiring();
    $updateProfile->execute(new UpdateBusinessProfileCommand($business->id->value, ['settings' => ['minimum_notice_hours' => 72]]));

    $error = null;

    try {
        $updateProfile->execute(new UpdateBusinessProfileCommand($business->id->value, ['settings' => ['booking_horizon_days' => 2]]));
    } catch (InvalidValueException $exception) {
        $error = $exception;
    }

    expect($error?->translationKey())->toBe('businesses.horizon_before_notice')
        ->and($businesses->find($business->id)?->settings->bookingHorizonDays)->toBe(30);
});

it('reports a business that does not exist', function () {
    [$updateProfile] = updateBusinessProfileWiring();

    $updateProfile->execute(new UpdateBusinessProfileCommand('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99', ['name' => 'Otra']));
})->throws(BusinessNotFoundException::class);

it('commits the profile changes in one transaction', function () {
    [$updateProfile, $business, , $transaction] = updateBusinessProfileWiring();

    $updateProfile->execute(new UpdateBusinessProfileCommand($business->id->value, ['name' => 'Archivum Norte']));

    expect($transaction->runs())->toBe(1)
        ->and($transaction->committed())->toBe(1)
        ->and($transaction->rolledBack())->toBe(0);
});

it('clears the place with null and retains its absence when another field changes', function () {
    [$updateProfile, $business] = updateBusinessProfileWiring();

    $cleared = $updateProfile->execute(new UpdateBusinessProfileCommand($business->id->value, ['appointment_place' => null]));
    expect($cleared->appointmentPlace)->toBeNull();

    $renamed = $updateProfile->execute(new UpdateBusinessProfileCommand($business->id->value, ['name' => 'Archivum Parfums']));
    expect($renamed->appointmentPlace)->toBeNull();
});

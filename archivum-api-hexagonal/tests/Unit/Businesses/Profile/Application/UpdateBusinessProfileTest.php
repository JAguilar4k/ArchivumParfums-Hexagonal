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
 * Wire the use case to an in-memory repository with a business whose margin is already changed.
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
        new BusinessSettings(bottleMarginColones: 7000),
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
        'settings' => ['decantSizesMl' => [3, 8], 'defaultGateway' => 'paypal', 'usdExchangeRate' => 512.5],
    ]));

    expect($updated->timezone->value)->toBe('America/Panama')
        ->and($updated->settings->bottleMarginColones)->toBe(7000)
        ->and($updated->settings->decantSizesMl)->toBe([3, 8])
        ->and($updated->settings->defaultGateway)->toBe('paypal')
        ->and($updated->settings->usdExchangeRate)->toBe(512.5)
        ->and($updated->settings->minimumNoticeHours)->toBe(12);
});

it('validates the horizon against the stored notice when only one of them changes', function () {
    [$updateProfile, $business, $businesses] = updateBusinessProfileWiring();
    $updateProfile->execute(new UpdateBusinessProfileCommand($business->id->value, ['settings' => ['minimumNoticeHours' => 72]]));

    $error = null;

    try {
        $updateProfile->execute(new UpdateBusinessProfileCommand($business->id->value, ['settings' => ['bookingHorizonDays' => 2]]));
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

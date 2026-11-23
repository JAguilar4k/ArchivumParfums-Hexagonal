<?php

declare(strict_types=1);

use Src\Businesses\Profile\Application\DTOs\RegisterBusinessCommand;
use Src\Businesses\Profile\Application\UseCases\RegisterBusiness;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSettings;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\AuditAction;
use Tests\Support\Businesses\InMemoryBusinessRepository;
use Tests\Support\Businesses\InMemoryOwnerAccounts;
use Tests\Support\Shared\RecordingAuditTrail;
use Tests\Support\Shared\RecordingTransactionRunner;

/**
 * Wire the use case to in-memory ports.
 *
 * @return array{RegisterBusiness, InMemoryBusinessRepository, InMemoryOwnerAccounts, RecordingTransactionRunner, RecordingAuditTrail}
 */
function registerBusinessWiring(): array
{
    $businesses = new InMemoryBusinessRepository;
    $owners = new InMemoryOwnerAccounts;
    $transaction = new RecordingTransactionRunner;
    $audit = new RecordingAuditTrail;

    return [new RegisterBusiness($businesses, $owners, $transaction, $audit), $businesses, $owners, $transaction, $audit];
}

/** Build the command of a business, overriding only what a scenario changes. */
function registerBusinessCommand(string $name = 'ArchivumParfums', string $slug = 'archivumparfums'): RegisterBusinessCommand
{
    return new RegisterBusinessCommand(
        $name,
        $slug,
        'Valeria Solano',
        'duena@archivumparfums.test',
        'clave-segura',
        'America/Costa_Rica',
        null,
        false,
    );
}

it('registers the business with the default settings and its owner in one transaction', function () {
    [$registerBusiness, $businesses, $owners, $transaction] = registerBusinessWiring();

    $business = $registerBusiness->execute(registerBusinessCommand());

    expect($business->name->value)->toBe('ArchivumParfums')
        ->and($business->slug->value)->toBe('archivumparfums')
        ->and($business->timezone->value)->toBe('America/Costa_Rica')
        ->and($business->appointmentPlace)->toBeNull()
        ->and($business->settings)->toEqual(BusinessSettings::defaults())
        ->and($businesses->all())->toEqual([$business])
        ->and($owners->registered())->toBe([[
            'businessId' => $business->id->value,
            'name' => 'Valeria Solano',
            'email' => 'duena@archivumparfums.test',
            'password' => 'clave-segura',
            'attendsAppointments' => false,
        ]])
        ->and($transaction->committed())->toBe(1);
});

it('leaves in the log of the new business that it was registered', function () {
    [$registerBusiness, , , , $audit] = registerBusinessWiring();

    $business = $registerBusiness->execute(registerBusinessCommand());

    expect($audit->entries)->toBe([[
        'action' => AuditAction::BusinessRegistered,
        'resource' => "business:{$business->id->value}",
        'before' => null,
        'after' => ['name' => 'ArchivumParfums', 'slug' => 'archivumparfums', 'timezone' => 'America/Costa_Rica'],
    ]]);
});

it('rolls the transaction back when the owner account is refused', function () {
    [$registerBusiness, , $owners, $transaction] = registerBusinessWiring();
    $failure = new InvalidValueException('accounts.email_already_registered');
    $owners->failWith($failure);

    expect(fn () => $registerBusiness->execute(registerBusinessCommand()))->toThrow($failure);

    expect($transaction->runs())->toBe(1)
        ->and($transaction->committed())->toBe(0)
        ->and($transaction->rolledBack())->toBe(1);
});

it('refuses an invalid name or slug before opening a transaction', function (string $name, string $slug, string $key) {
    [$registerBusiness, , $owners, $transaction] = registerBusinessWiring();

    expect(fn () => $registerBusiness->execute(registerBusinessCommand($name, $slug)))
        ->toThrow(InvalidValueException::class, $key);

    expect($transaction->runs())->toBe(0)
        ->and($owners->registered())->toBe([]);
})->with([
    'empty name' => ['  ', 'archivumparfums', 'businesses.name_invalid'],
    'slug with uppercase letters' => ['ArchivumParfums', 'Archivum', 'businesses.slug_invalid'],
]);

<?php

declare(strict_types=1);

use Src\Accounts\Sessions\Application\DTOs\StaffMembership;
use Src\Accounts\Sessions\Application\UseCases\RenewSession;
use Src\Accounts\Sessions\Domain\Exceptions\SessionNotRenewableException;
use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Tests\Support\Accounts\FakeTokenIssuer;
use Tests\Support\Accounts\InMemoryActiveTokens;
use Tests\Support\Accounts\InMemoryStaffMemberships;
use Tests\Support\Shared\RecordingTransactionRunner;

const RENEWING_ACCOUNT = '0192f0c4-0000-7000-8000-000000000103';

/**
 * Wire the use case to in-memory ports with one valid token, 7, of the account on the device Celular.
 *
 * @return array{RenewSession, FakeTokenIssuer, InMemoryStaffMemberships, RecordingTransactionRunner}
 */
function renewSessionWiring(): array
{
    $activeTokens = new InMemoryActiveTokens;
    $activeTokens->add(RENEWING_ACCOUNT, '7', 'Celular');
    $tokens = new FakeTokenIssuer;
    $memberships = new InMemoryStaffMemberships;
    $transactions = new RecordingTransactionRunner;

    return [new RenewSession($activeTokens, $tokens, $memberships, $transactions), $tokens, $memberships, $transactions];
}

it('swaps a valid token for a new one on the same device, with its expiration, in one transaction', function () {
    [$renewSession, $tokens, , $transactions] = renewSessionWiring();

    $session = $renewSession->execute(RENEWING_ACCOUNT, '7');

    expect($session->plainTextToken)->toBe('token-1')
        ->and($session->expiresAt)->toBe(FakeTokenIssuer::EXPIRES_AT)
        ->and($tokens->issued)->toBe([[RENEWING_ACCOUNT, 'Celular']])
        ->and($transactions->committed())->toBe(1);
});

it('gives the new token the abilities of the role the account has now', function (?AccountRole $staffRole, AccountRole $role) {
    [$renewSession, $tokens, $memberships] = renewSessionWiring();

    if ($staffRole !== null) {
        $memberships->add(RENEWING_ACCOUNT, new StaffMembership($staffRole, '0192f0c4-0000-7000-9000-000000000103', 'ArchivumParfums', 'archivumparfums'));
    }

    $renewSession->execute(RENEWING_ACCOUNT, '7');

    expect($tokens->abilities)->toBe([Ability::namesGrantedTo($role)]);
})->with([
    'owner' => [AccountRole::Owner, AccountRole::Owner],
    'staff' => [AccountRole::Staff, AccountRole::Staff],
    'customer' => [null, AccountRole::Customer],
]);

it('refuses a token that is no longer valid, also the one just renewed, and issues nothing', function () {
    [$renewSession, $tokens, , $transactions] = renewSessionWiring();
    $renewSession->execute(RENEWING_ACCOUNT, '7');

    expect(fn () => $renewSession->execute(RENEWING_ACCOUNT, '7'))
        ->toThrow(SessionNotRenewableException::class, 'accounts.session_not_renewable');

    expect($tokens->issued)->toHaveCount(1)
        ->and($transactions->rolledBack())->toBe(1);
});

it('refuses the token of another account', function () {
    [$renewSession, $tokens] = renewSessionWiring();

    expect(fn () => $renewSession->execute('0192f0c4-0000-7000-8000-000000000999', '7'))
        ->toThrow(SessionNotRenewableException::class);

    expect($tokens->issued)->toBe([]);
});

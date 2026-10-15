<?php

declare(strict_types=1);

use Src\Accounts\Sessions\Application\DTOs\StaffMembership;
use Src\Accounts\Sessions\Application\UseCases\GetCurrentAccount;
use Src\Accounts\Sessions\Domain\Exceptions\AccountNotFoundException;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Accounts\Users\Domain\Entities\User;
use Src\Accounts\Users\Domain\ValueObjects\Email;
use Src\Accounts\Users\Domain\ValueObjects\UserName;
use Tests\Support\Accounts\InMemoryStaffMemberships;
use Tests\Support\Accounts\InMemoryUserRepository;

/**
 * Wire the use case to in-memory ports with one account.
 *
 * @return array{GetCurrentAccount, InMemoryStaffMemberships, User}
 */
function currentAccountWiring(): array
{
    $users = new InMemoryUserRepository;
    $memberships = new InMemoryStaffMemberships;
    $user = User::create($users->nextId(), new UserName('Andrés Quesada'), new Email('asesor@archivum.test'), 'hash');
    $users->save($user);

    return [new GetCurrentAccount($users, $memberships), $memberships, $user];
}

it('describes a person of the staff with the role and the business of the membership', function () {
    [$getCurrentAccount, $memberships, $user] = currentAccountWiring();
    $membership = new StaffMembership(AccountRole::Staff, '0192f0c4-0000-7000-9000-000000000001', 'ArchivumParfums', 'archivumparfums');
    $memberships->add($user->id->value, $membership);

    $account = $getCurrentAccount->execute($user->id->value);

    expect($account->id)->toBe($user->id->value)
        ->and($account->name)->toBe('Andrés Quesada')
        ->and($account->email)->toBe('asesor@archivum.test')
        ->and($account->role)->toBe(AccountRole::Staff)
        ->and($account->membership)->toBe($membership);
});

it('describes an account without a membership as a customer without a business', function () {
    [$getCurrentAccount, , $user] = currentAccountWiring();

    $account = $getCurrentAccount->execute($user->id->value);

    expect($account->role)->toBe(AccountRole::Customer)
        ->and($account->membership)->toBeNull();
});

it('refuses an account that does not exist', function () {
    [$getCurrentAccount] = currentAccountWiring();

    $getCurrentAccount->execute('0192f0c4-0000-7000-8000-999999999999');
})->throws(AccountNotFoundException::class);

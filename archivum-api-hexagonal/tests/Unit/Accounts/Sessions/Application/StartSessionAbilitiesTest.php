<?php

declare(strict_types=1);

use Src\Accounts\Sessions\Application\DTOs\StaffMembership;
use Src\Accounts\Sessions\Application\DTOs\StartSessionCommand;
use Src\Accounts\Sessions\Application\UseCases\StartSession;
use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Accounts\Users\Domain\Entities\User;
use Src\Accounts\Users\Domain\ValueObjects\Email;
use Src\Accounts\Users\Domain\ValueObjects\UserName;
use Tests\Support\Accounts\FakePasswordHasher;
use Tests\Support\Accounts\FakeTokenIssuer;
use Tests\Support\Accounts\InMemoryPlatformAdmins;
use Tests\Support\Accounts\InMemoryStaffMemberships;
use Tests\Support\Accounts\InMemoryUserRepository;

it('issues the token with the abilities of the role of the account', function (?AccountRole $staffRole, bool $platformAdmin, AccountRole $role) {
    $users = new InMemoryUserRepository;
    $passwords = new FakePasswordHasher;
    $tokens = new FakeTokenIssuer;
    $memberships = new InMemoryStaffMemberships;
    $admins = new InMemoryPlatformAdmins;
    $user = User::create($users->nextId(), new UserName('Valeria Solano'), new Email('persona@archivum.test'), $passwords->hash('clave-segura'));
    $users->save($user);

    if ($staffRole !== null) {
        $memberships->add($user->id->value, new StaffMembership($staffRole, '0192f0c4-0000-7000-9000-000000000102', 'ArchivumParfums', 'archivumparfums'));
    }

    if ($platformAdmin) {
        $admins->grant($user->id);
    }

    new StartSession($users, $passwords, $tokens, $memberships, $admins)
        ->execute(new StartSessionCommand('persona@archivum.test', 'clave-segura', 'Computadora'));

    expect($tokens->abilities)->toBe([Ability::namesGrantedTo($role)]);
})->with([
    'owner' => [AccountRole::Owner, false, AccountRole::Owner],
    'staff' => [AccountRole::Staff, false, AccountRole::Staff],
    'customer, on no staff' => [null, false, AccountRole::Customer],
    'platform administrator' => [null, true, AccountRole::PlatformAdmin],
]);

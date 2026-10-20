<?php

declare(strict_types=1);

use Src\Accounts\Sessions\Application\DTOs\StaffMembership;
use Src\Accounts\Sessions\Application\UseCases\FindAccountRole;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Tests\Support\Accounts\InMemoryStaffMemberships;

const ROLE_ACCOUNT = '0192f0c4-0000-7000-8000-000000000021';

it('answers the role of the staff membership', function (AccountRole $role) {
    $memberships = new InMemoryStaffMemberships;
    $memberships->add(ROLE_ACCOUNT, new StaffMembership($role, '0192f0c4-0000-7000-9000-000000000021', 'ArchivumParfums', 'archivumparfums'));

    expect((new FindAccountRole($memberships))->execute(ROLE_ACCOUNT))->toBe($role);
})->with([AccountRole::Owner, AccountRole::Staff]);

it('answers customer for an account on no staff', function () {
    expect((new FindAccountRole(new InMemoryStaffMemberships))->execute(ROLE_ACCOUNT))->toBe(AccountRole::Customer);
});

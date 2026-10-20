<?php

declare(strict_types=1);

use Src\Businesses\Members\Application\DTOs\Membership;
use Src\Businesses\Members\Application\UseCases\FindMemberByAccount;
use Src\Businesses\Members\Domain\ValueObjects\MemberRole;
use Tests\Support\Businesses\InMemoryMemberships;

const FIND_MEMBER_USER = '0192f0c4-0000-7000-8000-000000000021';
const FIND_MEMBER_BUSINESS = '0192f0c4-0000-7000-9000-000000000021';

it('finds the business and the role of an account on a staff', function () {
    $memberships = new InMemoryMemberships;
    $memberships->add(FIND_MEMBER_USER, new Membership(FIND_MEMBER_BUSINESS, MemberRole::Owner));

    expect((new FindMemberByAccount($memberships))->execute(FIND_MEMBER_USER))
        ->toEqual(new Membership(FIND_MEMBER_BUSINESS, MemberRole::Owner));
});

it('finds nothing for an account on no staff', function () {
    expect((new FindMemberByAccount(new InMemoryMemberships))->execute(FIND_MEMBER_USER))->toBeNull();
});

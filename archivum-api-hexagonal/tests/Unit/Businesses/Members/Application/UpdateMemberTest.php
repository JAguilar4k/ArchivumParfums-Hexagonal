<?php

declare(strict_types=1);

use Src\Accounts\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Businesses\Members\Application\DTOs\RegisterMemberCommand;
use Src\Businesses\Members\Application\DTOs\UpdateMemberCommand;
use Src\Businesses\Members\Application\UseCases\AddMember;
use Src\Businesses\Members\Application\UseCases\GetMember;
use Src\Businesses\Members\Application\UseCases\IsAccountDeactivated;
use Src\Businesses\Members\Application\UseCases\ListMembers;
use Src\Businesses\Members\Application\UseCases\RegisterMember;
use Src\Businesses\Members\Application\UseCases\UpdateMember;
use Src\Businesses\Members\Domain\Entities\Member;
use Src\Businesses\Members\Domain\Exceptions\LastActiveOwnerException;
use Src\Businesses\Members\Domain\Exceptions\MemberNotFoundException;
use Src\Businesses\Members\Domain\ValueObjects\MemberRole;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\MemberId;
use Src\Shared\Domain\ValueObjects\UserId;
use Tests\Support\Businesses\InMemoryMemberDirectory;
use Tests\Support\Businesses\InMemoryMemberRepository;
use Tests\Support\Businesses\InMemoryStaffAccounts;

const MANAGE_BUSINESS = '0192f0c4-0000-7000-9000-000000000001';
const MANAGE_OTHER_BUSINESS = '0192f0c4-0000-7000-9000-000000000002';
const MANAGE_OWNER = '0192f0c4-0000-7000-8000-000000000011';
const MANAGE_STAFF = '0192f0c4-0000-7000-8000-000000000012';
const MANAGE_OWNER_USER = '0192f0c4-0000-7000-8000-000000000021';
const MANAGE_STAFF_USER = '0192f0c4-0000-7000-8000-000000000022';

/**
 * A business with an active owner and a member of its staff.
 *
 * @return array{InMemoryMemberRepository, InMemoryStaffAccounts, UpdateMember}
 */
function staffToManage(): array
{
    $members = new InMemoryMemberRepository;
    $accounts = new InMemoryStaffAccounts;
    $members->save(Member::reconstitute(new MemberId(MANAGE_OWNER), new UserId(MANAGE_OWNER_USER), new BusinessId(MANAGE_BUSINESS), MemberRole::Owner, true));
    $members->save(Member::reconstitute(new MemberId(MANAGE_STAFF), new UserId(MANAGE_STAFF_USER), new BusinessId(MANAGE_BUSINESS), MemberRole::Staff, false));

    return [$members, $accounts, new UpdateMember($members, new InMemoryMemberDirectory($members), $accounts)];
}

it('closes the sessions of a person whose role changes', function () {
    [, $accounts, $update] = staffToManage();

    $profile = $update->execute(new UpdateMemberCommand(MANAGE_BUSINESS, MANAGE_STAFF, null, MemberRole::Owner, null, null));

    expect($profile->role)->toBe(MemberRole::Owner)
        ->and($accounts->revoked)->toBe([MANAGE_STAFF_USER]);
});

it('closes the sessions of a person who is deactivated', function () {
    [, $accounts, $update] = staffToManage();

    $profile = $update->execute(new UpdateMemberCommand(MANAGE_BUSINESS, MANAGE_STAFF, null, null, null, false));

    expect($profile->isActive)->toBeFalse()
        ->and($accounts->revoked)->toBe([MANAGE_STAFF_USER]);
});

it('keeps the sessions when the role sent is the one the person already has, or only the name changes', function () {
    [, $accounts, $update] = staffToManage();

    $update->execute(new UpdateMemberCommand(MANAGE_BUSINESS, MANAGE_STAFF, 'Nuevo', MemberRole::Staff, true, true));

    expect($accounts->revoked)->toBe([])
        ->and($accounts->renamed)->toBe([MANAGE_STAFF_USER => 'Nuevo']);
});

it('refuses to deactivate the last active owner', function () {
    [, , $update] = staffToManage();

    $update->execute(new UpdateMemberCommand(MANAGE_BUSINESS, MANAGE_OWNER, null, null, null, false));
})->throws(LastActiveOwnerException::class);

it('refuses to take the role from the last active owner', function () {
    [, , $update] = staffToManage();

    $update->execute(new UpdateMemberCommand(MANAGE_BUSINESS, MANAGE_OWNER, null, MemberRole::Staff, null, null));
})->throws(LastActiveOwnerException::class);

it('does not look for a person of another business', function () {
    [, , $update] = staffToManage();

    $update->execute(new UpdateMemberCommand(MANAGE_OTHER_BUSINESS, MANAGE_STAFF, 'Intruso', null, null, null));
})->throws(MemberNotFoundException::class);

it('registers a new person by opening the account and adding it to the staff', function () {
    $members = new InMemoryMemberRepository;
    $register = new RegisterMember(new InMemoryStaffAccounts, new AddMember($members));

    $profile = $register->execute(new RegisterMemberCommand(MANAGE_BUSINESS, 'Andrea', ' ANDREA@personal.test ', 'una-clave-larga', MemberRole::Staff, true));

    expect($profile->email)->toBe('andrea@personal.test')
        ->and($profile->isActive)->toBeTrue()
        ->and($members->all())->toHaveCount(1)
        ->and($members->all()[0]->businessId->value)->toBe(MANAGE_BUSINESS);
});

it('does not add anybody when the email is already registered', function () {
    $members = new InMemoryMemberRepository;
    $register = new RegisterMember(new InMemoryStaffAccounts(refuseEmails: true), new AddMember($members));

    expect(fn () => $register->execute(new RegisterMemberCommand(MANAGE_BUSINESS, 'Andrea', 'andrea@personal.test', 'una-clave-larga', MemberRole::Staff, true)))
        ->toThrow(EmailAlreadyRegisteredException::class)
        ->and($members->all())->toBe([]);
});

it('lists and finds the people of one business only', function () {
    [$members] = staffToManage();
    $directory = new InMemoryMemberDirectory($members);

    expect(new ListMembers($directory)->execute(MANAGE_BUSINESS))->toHaveCount(2)
        ->and(new ListMembers($directory)->execute(MANAGE_OTHER_BUSINESS))->toBe([])
        ->and(new GetMember($directory)->execute(MANAGE_BUSINESS, MANAGE_STAFF)->id)->toBe(MANAGE_STAFF);

    new GetMember($directory)->execute(MANAGE_OTHER_BUSINESS, MANAGE_STAFF);
})->throws(MemberNotFoundException::class);

it('normalises the email it asks about', function () {
    $accounts = new InMemoryStaffAccounts(deactivatedEmails: ['andrea@personal.test']);

    expect(new IsAccountDeactivated($accounts)->execute(' ANDREA@personal.test '))->toBeTrue()
        ->and(new IsAccountDeactivated($accounts)->execute('otra@personal.test'))->toBeFalse();
});

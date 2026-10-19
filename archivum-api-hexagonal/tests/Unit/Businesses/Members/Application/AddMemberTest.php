<?php

declare(strict_types=1);

use Src\Businesses\Members\Application\DTOs\AddMemberCommand;
use Src\Businesses\Members\Application\UseCases\AddMember;
use Src\Businesses\Members\Domain\Exceptions\AlreadyAMemberException;
use Src\Businesses\Members\Domain\ValueObjects\MemberRole;
use Tests\Support\Businesses\InMemoryMemberRepository;

const ADD_MEMBER_USER = '0192f0c4-0000-7000-8000-000000000001';
const ADD_MEMBER_BUSINESS = '0192f0c4-0000-7000-9000-000000000001';
const ADD_MEMBER_OTHER_BUSINESS = '0192f0c4-0000-7000-9000-000000000002';

it('adds an account to the staff of a business with a new identity and its role', function () {
    $members = new InMemoryMemberRepository;

    $member = (new AddMember($members))->execute(new AddMemberCommand(ADD_MEMBER_USER, ADD_MEMBER_BUSINESS, MemberRole::Staff, true));

    expect($member->id->value)->toBe('0192f0c4-0000-7000-8000-000000000001')
        ->and($member->role)->toBe(MemberRole::Staff)
        ->and($member->attendsAppointments)->toBeTrue()
        ->and($members->all())->toEqual([$member]);
});

it('refuses an account that already belongs to another business', function () {
    $addMember = new AddMember(new InMemoryMemberRepository);
    $addMember->execute(new AddMemberCommand(ADD_MEMBER_USER, ADD_MEMBER_BUSINESS, MemberRole::Owner, false));

    $addMember->execute(new AddMemberCommand(ADD_MEMBER_USER, ADD_MEMBER_OTHER_BUSINESS, MemberRole::Staff, false));
})->throws(AlreadyAMemberException::class);

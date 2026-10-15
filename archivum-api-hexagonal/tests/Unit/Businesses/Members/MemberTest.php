<?php

declare(strict_types=1);

use Src\Businesses\Members\Domain\Entities\Member;
use Src\Businesses\Members\Domain\ValueObjects\MemberRole;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\UserId;

const MEMBER_USER_UUID = '0190a1b2-c3d4-7e5f-8a6b-aaaaaaaaaaaa';
const MEMBER_BUSINESS_UUID = '0190a1b2-c3d4-7e5f-8a6b-bbbbbbbbbbbb';

it('links one account to one business with a role', function (): void {
    $member = Member::create(
        new UserId(MEMBER_USER_UUID),
        new BusinessId(MEMBER_BUSINESS_UUID),
        MemberRole::Staff,
    );

    expect($member->userId->value)->toBe(MEMBER_USER_UUID)
        ->and($member->businessId->value)->toBe(MEMBER_BUSINESS_UUID)
        ->and($member->role)->toBe(MemberRole::Staff)
        ->and($member->isOwner())->toBeFalse()
        ->and($member->attendsAppointments)->toBeFalse();
});

it('lets an owner attend appointments', function (): void {
    $member = Member::create(
        new UserId(MEMBER_USER_UUID),
        new BusinessId(MEMBER_BUSINESS_UUID),
        MemberRole::Owner,
        attendsAppointments: true,
    );

    expect($member->isOwner())->toBeTrue()
        ->and($member->attendsAppointments)->toBeTrue();
});

it('only accepts the owner and staff roles', function (): void {
    expect(MemberRole::from('owner'))->toBe(MemberRole::Owner)
        ->and(MemberRole::from('staff'))->toBe(MemberRole::Staff)
        ->and(MemberRole::tryFrom('admin'))->toBeNull();
});

it('reconstitutes a member and keeps the constructor private', function (): void {
    $member = Member::reconstitute(
        new UserId(MEMBER_USER_UUID),
        new BusinessId(MEMBER_BUSINESS_UUID),
        MemberRole::Staff,
        true,
    );

    expect($member->attendsAppointments)->toBeTrue()
        ->and((new ReflectionClass(Member::class))->getConstructor()?->isPrivate())->toBeTrue();
});

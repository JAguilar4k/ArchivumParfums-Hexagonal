<?php

declare(strict_types=1);

use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Domain\Entities\Member;
use Src\Businesses\Members\Domain\Exceptions\AccountAlreadyOnStaffException;
use Src\Businesses\Members\Domain\ValueObjects\MemberRole;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Businesses\Members\Infrastructure\Persistence\Repositories\EloquentMemberRepository;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\UserId;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

it('persists the aggregate identity and retains it when the member changes', function () {
    $business = BusinessModel::factory()->create();
    $user = UserModel::factory()->create();
    $this->scopeQueriesTo($business->id);
    $repository = new EloquentMemberRepository;
    $id = $repository->nextId();
    $member = Member::create($id, new UserId($user->id), new BusinessId($business->id), MemberRole::Staff);

    $repository->save($member);
    $repository->save(Member::reconstitute($id, $member->userId, $member->businessId, MemberRole::Owner, true));

    assertDatabaseCount('members', 1);
    assertDatabaseHas('members', [
        'id' => $id->value,
        'user_id' => $user->id,
        'business_id' => $business->id,
        'role' => 'owner',
        'attends_appointments' => true,
    ]);
});

it('raises the domain error for an account already on a staff and keeps the transaction usable', function () {
    $existing = MemberModel::factory()->owner()->create();
    $business = BusinessModel::factory()->create();
    $this->scopeQueriesTo($business->id);
    $repository = new EloquentMemberRepository;
    $member = Member::create(
        $repository->nextId(),
        new UserId($existing->user_id),
        new BusinessId($business->id),
        MemberRole::Staff,
    );

    expect(fn () => $repository->save($member))->toThrow(AccountAlreadyOnStaffException::class);

    assertDatabaseCount('members', 1);
});

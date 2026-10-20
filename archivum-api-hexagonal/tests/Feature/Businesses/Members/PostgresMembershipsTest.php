<?php

declare(strict_types=1);

use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Application\DTOs\Membership;
use Src\Businesses\Members\Domain\ValueObjects\MemberRole;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Businesses\Members\Infrastructure\Persistence\Queries\PostgresMemberships;
use Src\Shared\Domain\ValueObjects\UserId;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

it('finds the business and the role of each person of the staff with no business pinned', function (string $state, MemberRole $role) {
    $member = MemberModel::factory()->{$state}()->create();
    $this->scopeQueriesTo(null);

    expect((new PostgresMemberships)->of(new UserId($member->user_id)))->toEqual(new Membership($member->business_id, $role));
})->with([
    'owner' => ['owner', MemberRole::Owner],
    'staff' => ['staff', MemberRole::Staff],
]);

it('finds nothing for an account on no staff', function () {
    expect((new PostgresMemberships)->of(new UserId(UserModel::factory()->create()->id)))->toBeNull();
});

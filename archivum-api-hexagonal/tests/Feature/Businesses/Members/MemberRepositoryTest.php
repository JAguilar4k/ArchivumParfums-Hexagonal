<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Domain\Contracts\MemberRepository;
use Src\Businesses\Members\Domain\Entities\Member;
use Src\Businesses\Members\Domain\ValueObjects\MemberRole;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\UserId;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

pest()->use(RefreshDatabaseAsOwner::class);

it('persists the aggregate identity and retains it when the member changes', function () {
    $business = BusinessModel::factory()->create();
    $user = UserModel::factory()->create();
    DB::select('select set_config(?, ?, true)', ['app.business_id', $business->id]);
    $repository = app(MemberRepository::class);
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

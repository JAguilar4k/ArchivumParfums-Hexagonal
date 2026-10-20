<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Laravel\Sanctum\Sanctum;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Businesses\Members\Infrastructure\Tenancy\StaffBusinessContext;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Infrastructure\Tenancy\ScopeRequestToBusiness;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

/** The adapter that the business:staff middleware gives to the panel routes. */
function staffBusinessContext(): BusinessContext
{
    $context = app(ScopeRequestToBusiness::contextFor(ScopeRequestToBusiness::STAFF));
    assert($context instanceof BusinessContext);

    return $context;
}

it('binds the panel routes to the adapter that reads the session of the staff', function () {
    expect(staffBusinessContext())->toBeInstanceOf(StaffBusinessContext::class);
});

it('gives each person of the staff the business of the account', function (string $state) {
    $member = MemberModel::factory()->{$state}()->create();
    Sanctum::actingAs(UserModel::query()->whereKey($member->user_id)->sole());

    expect(staffBusinessContext()->current()->value)->toBe($member->business_id);
})->with(['owner' => 'owner', 'staff' => 'staff']);

it('forbids a customer, who is on the staff of no business', function () {
    Sanctum::actingAs(UserModel::factory()->create());

    staffBusinessContext()->current();
})->throws(AuthorizationException::class);

it('has no business to give without an authenticated person', function () {
    staffBusinessContext()->current();
})->throws(AuthenticationException::class);

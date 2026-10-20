<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Src\Accounts\Sessions\Infrastructure\Http\Middleware\RequireAccountRole;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

/** Authenticate a new account: a member of the staff in the given factory state or, without one, a customer. */
function actAsAccountOfRole(?string $memberState): void
{
    $user = $memberState === null
        ? UserModel::factory()->create()
        : UserModel::query()->whereKey(MemberModel::factory()->{$memberState}()->create()->user_id)->sole();

    Sanctum::actingAs($user);
}

/**
 * Run the role middleware with its roles and tell whether the request reached the route behind it.
 *
 * @param  list<string>  $roles
 */
function passesRoleMiddleware(array $roles): bool
{
    $reached = false;

    app(RequireAccountRole::class)->handle(Request::create('/'), function () use (&$reached): Response {
        $reached = true;

        return response()->noContent();
    }, ...$roles);

    return $reached;
}

it('lets through an account with one of the roles', function (?string $memberState, array $roles) {
    actAsAccountOfRole($memberState);

    expect(passesRoleMiddleware($roles))->toBeTrue();
})->with([
    'owner in role:owner' => ['owner', ['owner']],
    'staff in role:owner,staff' => ['staff', ['owner', 'staff']],
    'customer in role:customer' => [null, ['customer']],
]);

it('forbids an account with none of the roles', function (?string $memberState, array $roles) {
    actAsAccountOfRole($memberState);

    passesRoleMiddleware($roles);
})->with([
    'staff in role:owner' => ['staff', ['owner']],
    'customer in role:owner' => [null, ['owner']],
    'owner in role:customer' => ['owner', ['customer']],
])->throws(AuthorizationException::class);

it('asks for an authenticated account', function () {
    passesRoleMiddleware(['owner']);
})->throws(AuthenticationException::class);

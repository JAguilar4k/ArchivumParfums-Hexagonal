<?php

declare(strict_types=1);

use Src\Accounts\Sessions\Infrastructure\Security\SanctumTokenRevoker;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Domain\ValueObjects\UserId;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseMissing;

pest()->use(RefreshDatabaseAsOwner::class);

it('deletes the row of one token and keeps the other tokens of the account', function () {
    $user = UserModel::factory()->create();
    $closed = $user->createToken('Celular')->accessToken;
    $user->createToken('Computadora');

    new SanctumTokenRevoker()->revoke((string) $closed->getKey());

    assertDatabaseMissing('personal_access_tokens', ['id' => $closed->getKey()]);
    expect($user->tokens()->pluck('name')->all())->toBe(['Computadora']);
});

it('leaves every token in place for an unknown token', function () {
    UserModel::factory()->create()->createToken('Celular');

    new SanctumTokenRevoker()->revoke('999');

    assertDatabaseCount('personal_access_tokens', 1);
});

it('revokes every token of one account and no other', function () {
    $closed = UserModel::factory()->create();
    $closed->createToken('Celular');
    $closed->createToken('Computadora');
    $other = UserModel::factory()->create();
    $other->createToken('Celular');

    new SanctumTokenRevoker()->revokeAll(new UserId($closed->id));

    expect($closed->tokens()->count())->toBe(0)
        ->and($other->tokens()->count())->toBe(1);
});

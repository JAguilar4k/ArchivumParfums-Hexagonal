<?php

declare(strict_types=1);

use Laravel\Sanctum\PersonalAccessToken;
use Src\Accounts\Sessions\Infrastructure\Security\SanctumTokenIssuer;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Domain\ValueObjects\UserId;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

pest()->use(RefreshDatabaseAsOwner::class);

it('stores the token as a hash, named after the device, and never the secret', function () {
    $user = UserModel::factory()->create();

    $plainTextToken = new SanctumTokenIssuer()->issue(new UserId($user->id), 'Celular de la tienda', ['customer:own']);

    [$id, $secret] = explode('|', $plainTextToken, 2);
    assertDatabaseHas('personal_access_tokens', [
        'id' => $id,
        'tokenable_id' => $user->id,
        'name' => 'Celular de la tienda',
        'token' => hash('sha256', $secret),
    ]);
    assertDatabaseMissing('personal_access_tokens', ['token' => $secret]);
    expect(PersonalAccessToken::findToken($plainTextToken)?->tokenable?->is($user))->toBeTrue();
});

it('gives every device its own token, so each one can be closed on its own', function () {
    $user = UserModel::factory()->create();
    $issuer = new SanctumTokenIssuer;

    $first = $issuer->issue(new UserId($user->id), 'Celular', ['customer:own']);
    $second = $issuer->issue(new UserId($user->id), 'Computadora', ['customer:own']);

    expect($first)->not->toBe($second);
    assertDatabaseCount('personal_access_tokens', 2);
});

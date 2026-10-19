<?php

declare(strict_types=1);

use Src\Accounts\Sessions\Application\UseCases\EndSession;
use Tests\Support\Accounts\FakeTokenRevoker;

it('revokes only the token of the session', function () {
    $tokens = new FakeTokenRevoker;

    (new EndSession($tokens))->execute('42');

    expect($tokens->revoked)->toBe(['42'])
        ->and($tokens->revokedAccounts)->toBe([]);
});

<?php

declare(strict_types=1);

use Src\Accounts\Sessions\Application\UseCases\EndSession;
use Tests\Support\Accounts\FakeTokenRevoker;

it('revokes only the token of the session', function () {
    $tokens = new FakeTokenRevoker;

    (new EndSession($tokens))->execute('0192f0c4-0000-7000-a000-000000000001');

    expect($tokens->revoked)->toBe(['0192f0c4-0000-7000-a000-000000000001'])
        ->and($tokens->revokedAccounts)->toBe([]);
});

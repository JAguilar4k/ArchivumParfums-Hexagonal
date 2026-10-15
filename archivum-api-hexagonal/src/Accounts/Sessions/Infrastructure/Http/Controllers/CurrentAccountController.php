<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Infrastructure\Http\Controllers;

use Illuminate\Http\Request;
use Src\Accounts\Sessions\Application\UseCases\GetCurrentAccount;
use Src\Accounts\Sessions\Infrastructure\Http\Resources\CurrentAccount;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;

final readonly class CurrentAccountController
{
    /** Answer the data and the role of the account that owns the token. */
    public function show(Request $request, GetCurrentAccount $getCurrentAccount): CurrentAccount
    {
        /** @var UserModel $user */
        $user = $request->user();

        return new CurrentAccount($getCurrentAccount->execute($user->id));
    }
}

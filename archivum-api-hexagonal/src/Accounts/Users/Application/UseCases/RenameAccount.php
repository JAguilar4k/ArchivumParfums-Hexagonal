<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Application\UseCases;

use Src\Accounts\Sessions\Domain\Exceptions\AccountNotFoundException;
use Src\Accounts\Users\Domain\Contracts\UserRepository;
use Src\Accounts\Users\Domain\Entities\User;
use Src\Accounts\Users\Domain\ValueObjects\UserName;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class RenameAccount
{
    /** Receive the user repository port. */
    public function __construct(private UserRepository $users) {}

    /**
     * Change the name of an account and nothing else.
     *
     * @throws AccountNotFoundException
     */
    public function execute(string $userId, string $name): void
    {
        $id = new UserId($userId);
        $user = $this->users->find($id) ?? throw AccountNotFoundException::create();

        $this->users->save(User::reconstitute($user->id, new UserName($name), $user->email, $user->passwordHash));
    }
}

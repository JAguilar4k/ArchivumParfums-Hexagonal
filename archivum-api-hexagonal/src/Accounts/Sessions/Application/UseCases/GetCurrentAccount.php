<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Application\UseCases;

use Src\Accounts\Sessions\Application\Contracts\StaffMemberships;
use Src\Accounts\Sessions\Application\DTOs\CurrentAccount;
use Src\Accounts\Sessions\Domain\Exceptions\AccountNotFoundException;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Accounts\Users\Domain\Contracts\UserRepository;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class GetCurrentAccount
{
    /** Receive the user repository and the staff memberships ports. */
    public function __construct(
        private UserRepository $users,
        private StaffMemberships $memberships,
    ) {}

    /**
     * Describe the signed-in account: its role comes from the staff and, without a membership, it is a customer.
     *
     * @throws AccountNotFoundException
     */
    public function execute(string $userId): CurrentAccount
    {
        $id = new UserId($userId);
        $user = $this->users->find($id) ?? throw AccountNotFoundException::create();
        $membership = $this->memberships->of($id);

        return new CurrentAccount(
            $user->id->value,
            $user->name->value,
            $user->email->value,
            $membership === null ? AccountRole::Customer : $membership->role,
            $membership,
        );
    }
}

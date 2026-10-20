<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Domain\Contracts;

use Src\Accounts\Users\Domain\Entities\User;
use Src\Accounts\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Accounts\Users\Domain\ValueObjects\Email;
use Src\Shared\Domain\ValueObjects\UserId;

interface UserRepository
{
    /** Generate the identity for a new account. */
    public function nextId(): UserId;

    /**
     * Persist a new or modified account.
     *
     * @throws EmailAlreadyRegisteredException
     */
    public function save(User $user): void;

    /** Find the account registered with an email address. */
    public function findByEmail(Email $email): ?User;

    /** Find an account by its identity. */
    public function find(UserId $id): ?User;
}

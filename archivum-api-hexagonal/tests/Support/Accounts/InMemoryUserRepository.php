<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use Src\Accounts\Users\Domain\Contracts\UserRepository;
use Src\Accounts\Users\Domain\Entities\User;
use Src\Accounts\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Accounts\Users\Domain\ValueObjects\Email;
use Src\Shared\Domain\ValueObjects\UserId;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> */
    private array $users = [];

    private int $sequence = 0;

    /** Generate a predictable identity. */
    public function nextId(): UserId
    {
        return new UserId(sprintf('0192f0c4-0000-7000-8000-%012d', ++$this->sequence));
    }

    /** Store the account enforcing one account per email. */
    public function save(User $user): void
    {
        $existing = $this->findByEmail($user->email);

        if ($existing !== null && ! $existing->id->equals($user->id)) {
            throw EmailAlreadyRegisteredException::create();
        }

        $this->users[$user->id->value] = $user;
    }

    /** Find the account registered with an email address. */
    public function findByEmail(Email $email): ?User
    {
        return array_find($this->users, fn (User $user): bool => $user->email->value === $email->value);
    }

    /** Find an account by its identity. */
    public function find(UserId $id): ?User
    {
        return $this->users[$id->value] ?? null;
    }

    /** Count the stored accounts. */
    public function count(): int
    {
        return count($this->users);
    }
}

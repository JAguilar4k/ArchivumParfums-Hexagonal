<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Domain\Entities;

use Src\Accounts\Users\Domain\ValueObjects\Email;
use Src\Accounts\Users\Domain\ValueObjects\UserName;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class User
{
    /** Hold the full state of an account; its role in a business lives in members or customers. */
    private function __construct(
        public UserId $id,
        public UserName $name,
        public Email $email,
        public string $passwordHash,
    ) {}

    /** Register a new account with an already hashed password. */
    public static function create(UserId $id, UserName $name, Email $email, string $passwordHash): self
    {
        return new self($id, $name, $email, $passwordHash);
    }

    /** Rebuild an account from its stored state. */
    public static function reconstitute(UserId $id, UserName $name, Email $email, string $passwordHash): self
    {
        return new self($id, $name, $email, $passwordHash);
    }
}

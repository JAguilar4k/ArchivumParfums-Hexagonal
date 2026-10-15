<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure\Security;

use Illuminate\Support\Facades\Hash;
use SensitiveParameter;
use Src\Accounts\Users\Domain\Contracts\PasswordHasher;

final readonly class BcryptPasswordHasher implements PasswordHasher
{
    private const string DRIVER = 'bcrypt';

    /** Hash the password with bcrypt and the configured number of rounds. */
    public function hash(#[SensitiveParameter] string $plainPassword): string
    {
        return Hash::driver(self::DRIVER)->make($plainPassword);
    }

    /** Compare the password with a bcrypt hash; without one, hash it anyway so the time is the same. */
    public function check(#[SensitiveParameter] string $plainPassword, ?string $hash): bool
    {
        if ($hash === null) {
            $this->hash($plainPassword);

            return false;
        }

        return Hash::driver(self::DRIVER)->check($plainPassword, $hash);
    }
}

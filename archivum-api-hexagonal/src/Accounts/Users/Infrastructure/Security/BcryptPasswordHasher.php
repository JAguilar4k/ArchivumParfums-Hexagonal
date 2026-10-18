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

    /** Use the same bcrypt cost for an unknown account or an input bcrypt cannot hash. */
    public function check(#[SensitiveParameter] string $plainPassword, ?string $hash): bool
    {
        if ($hash === null || str_contains($plainPassword, "\0")) {
            $this->hash('archivum-invalid-credentials');

            return false;
        }

        return Hash::driver(self::DRIVER)->check($plainPassword, $hash);
    }
}

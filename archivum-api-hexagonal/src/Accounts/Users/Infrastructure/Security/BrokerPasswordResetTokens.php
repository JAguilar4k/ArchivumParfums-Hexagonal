<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure\Security;

use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;
use SensitiveParameter;
use Src\Accounts\Users\Domain\Contracts\PasswordResetTokens;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class BrokerPasswordResetTokens implements PasswordResetTokens
{
    /** Create the token with the broker of Laravel, which stores only its hash; a recent one counts as passwords.throttled. */
    public function issue(UserId $userId): ?string
    {
        $user = UserModel::query()->findOrFail($userId->value);

        if ($this->broker()->getRepository()->recentlyCreatedToken($user)) {
            return null;
        }

        return $this->broker()->createToken($user);
    }

    /** Ask the broker whether the token matches the hash stored for the account and has not expired. */
    public function isValid(UserId $userId, #[SensitiveParameter] string $token): bool
    {
        return $this->broker()->tokenExists(UserModel::query()->findOrFail($userId->value), $token);
    }

    /** Delete the row of the account in password_reset_tokens. */
    public function revoke(UserId $userId): void
    {
        $this->broker()->deleteToken(UserModel::query()->findOrFail($userId->value));
    }

    /** The broker configured in config/auth.php. */
    private function broker(): PasswordBroker
    {
        $broker = Password::broker();
        assert($broker instanceof PasswordBroker);

        return $broker;
    }
}

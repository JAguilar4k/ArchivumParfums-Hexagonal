<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Application\UseCases;

use Src\Accounts\Users\Domain\Contracts\PasswordResetNotifier;
use Src\Accounts\Users\Domain\Contracts\PasswordResetTokens;
use Src\Accounts\Users\Domain\Contracts\UserRepository;
use Src\Accounts\Users\Domain\ValueObjects\Email;

final readonly class RequestPasswordReset
{
    /** Receive the user repository, the recovery token and the notification ports. */
    public function __construct(
        private UserRepository $users,
        private PasswordResetTokens $tokens,
        private PasswordResetNotifier $notifier,
    ) {}

    /**
     * Send the recovery link when the email has an account and it got no link a moment ago; otherwise do nothing, so
     * nobody learns from this whether an account exists.
     */
    public function execute(string $email): void
    {
        $user = $this->users->findByEmail(new Email($email));

        if ($user === null) {
            return;
        }

        $token = $this->tokens->issue($user->id);

        if ($token !== null) {
            $this->notifier->send($user->email->value, $token);
        }
    }
}

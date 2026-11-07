<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Application\UseCases;

use Src\Accounts\Sessions\Domain\Contracts\TokenRevoker;
use Src\Accounts\Users\Application\DTOs\ResetPasswordCommand;
use Src\Accounts\Users\Domain\Contracts\PasswordHasher;
use Src\Accounts\Users\Domain\Contracts\PasswordResetTokens;
use Src\Accounts\Users\Domain\Contracts\UserRepository;
use Src\Accounts\Users\Domain\Entities\User;
use Src\Accounts\Users\Domain\Exceptions\InvalidResetTokenException;
use Src\Accounts\Users\Domain\ValueObjects\Email;
use Src\Accounts\Users\Domain\ValueObjects\PlainPassword;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class ResetPassword
{
    /** Receive the user repository, the recovery token, the password hashing, the session and the transaction ports. */
    public function __construct(
        private UserRepository $users,
        private PasswordResetTokens $tokens,
        private PasswordHasher $passwords,
        private TokenRevoker $sessions,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Set a new password with a valid token, spend the token and close every session of the account. An email without
     * an account answers as a wrong token.
     *
     * @throws InvalidResetTokenException
     */
    public function execute(ResetPasswordCommand $command): void
    {
        $user = $this->users->findByEmail(new Email($command->email));

        if ($user === null || ! $this->tokens->isValid($user->id, $command->token)) {
            throw InvalidResetTokenException::create();
        }

        $hash = $this->passwords->hash(new PlainPassword($command->newPassword)->value);

        $this->transaction->run(function () use ($user, $hash): void {
            $this->users->save(User::reconstitute($user->id, $user->name, $user->email, $hash));
            $this->tokens->revoke($user->id);
            $this->sessions->revokeAll($user->id);
        });
    }
}

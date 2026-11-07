<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Application\UseCases;

use Src\Accounts\Sessions\Domain\Contracts\TokenRevoker;
use Src\Accounts\Sessions\Domain\Exceptions\AccountNotFoundException;
use Src\Accounts\Users\Application\DTOs\ChangePasswordCommand;
use Src\Accounts\Users\Domain\Contracts\PasswordHasher;
use Src\Accounts\Users\Domain\Contracts\UserRepository;
use Src\Accounts\Users\Domain\Entities\User;
use Src\Accounts\Users\Domain\Exceptions\InvalidCurrentPasswordException;
use Src\Accounts\Users\Domain\ValueObjects\PlainPassword;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class ChangePassword
{
    /** Receive the user repository, the password hashing, the session and the transaction ports. */
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwords,
        private TokenRevoker $tokens,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Change the password of an account that proves the current one, and close its other sessions.
     *
     * @throws AccountNotFoundException
     * @throws InvalidCurrentPasswordException
     */
    public function execute(ChangePasswordCommand $command): void
    {
        $id = new UserId($command->userId);
        $user = $this->users->find($id) ?? throw AccountNotFoundException::create();

        if (! $this->passwords->check($command->currentPassword, $user->passwordHash)) {
            throw InvalidCurrentPasswordException::create();
        }

        $hash = $this->passwords->hash(new PlainPassword($command->newPassword)->value);

        $this->transaction->run(function () use ($user, $hash, $command): void {
            $this->users->save(User::reconstitute($user->id, $user->name, $user->email, $hash));
            $this->tokens->revokeAllExcept($user->id, $command->currentTokenId);
        });
    }
}

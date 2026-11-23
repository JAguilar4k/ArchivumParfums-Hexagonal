<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Application\UseCases;

use Src\Accounts\Users\Application\DTOs\RegisterAccountCommand;
use Src\Accounts\Users\Domain\Contracts\PasswordHasher;
use Src\Accounts\Users\Domain\Contracts\PlatformAdmins;
use Src\Accounts\Users\Domain\Contracts\UserRepository;
use Src\Accounts\Users\Domain\Entities\User;
use Src\Accounts\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Accounts\Users\Domain\ValueObjects\Email;
use Src\Accounts\Users\Domain\ValueObjects\PlainPassword;
use Src\Accounts\Users\Domain\ValueObjects\UserName;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class RegisterPlatformAdmin
{
    /** Receive the user repository, the password hasher, the platform administrators and the transaction ports. */
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private PlatformAdmins $platformAdmins,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Open a new account and mark it as an administrator of the platform, together. An email that already has an
     * account is refused, so a person of a staff or a customer never becomes an administrator by mistake.
     *
     * @throws EmailAlreadyRegisteredException
     */
    public function execute(RegisterAccountCommand $command): User
    {
        $name = new UserName($command->name);
        $email = new Email($command->email);
        $password = new PlainPassword($command->password);

        return $this->transaction->run(function () use ($name, $email, $password): User {
            $user = User::create($this->users->nextId(), $name, $email, $this->passwordHasher->hash($password->value));

            $this->users->save($user);
            $this->platformAdmins->grant($user->id);

            return $user;
        });
    }
}

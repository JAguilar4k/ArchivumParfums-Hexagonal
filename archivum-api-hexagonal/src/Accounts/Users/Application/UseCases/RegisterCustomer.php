<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Application\UseCases;

use Src\Accounts\Users\Application\DTOs\RegisterAccountCommand;
use Src\Accounts\Users\Domain\Contracts\PasswordHasher;
use Src\Accounts\Users\Domain\Contracts\UserRepository;
use Src\Accounts\Users\Domain\Entities\User;
use Src\Accounts\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Accounts\Users\Domain\ValueObjects\Email;
use Src\Accounts\Users\Domain\ValueObjects\PlainPassword;
use Src\Accounts\Users\Domain\ValueObjects\UserName;

final readonly class RegisterCustomer
{
    /** Receive the user repository and password hasher ports. */
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
    ) {}

    /**
     * Open the account of a customer with a hashed password. An account without a row in members is a customer, so
     * nothing else is written: its row in customers comes when it first operates in a business (#62).
     *
     * @throws EmailAlreadyRegisteredException
     */
    public function execute(RegisterAccountCommand $command): User
    {
        $name = new UserName($command->name);
        $email = new Email($command->email);
        $password = new PlainPassword($command->password);

        $user = User::create(
            $this->users->nextId(),
            $name,
            $email,
            $this->passwordHasher->hash($password->value),
        );

        // The unique key on users.email settles two simultaneous sign-ups with the same address.
        $this->users->save($user);

        return $user;
    }
}

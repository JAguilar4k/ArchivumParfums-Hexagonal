<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Application\UseCases;

use Src\Accounts\Sessions\Application\DTOs\StartedSession;
use Src\Accounts\Sessions\Application\DTOs\StartSessionCommand;
use Src\Accounts\Sessions\Domain\Contracts\TokenIssuer;
use Src\Accounts\Sessions\Domain\Exceptions\InvalidCredentialsException;
use Src\Accounts\Users\Domain\Contracts\PasswordHasher;
use Src\Accounts\Users\Domain\Contracts\UserRepository;
use Src\Accounts\Users\Domain\ValueObjects\Email;

final readonly class StartSession
{
    /** Receive the user repository, the password hashing and the token ports. */
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwords,
        private TokenIssuer $tokens,
    ) {}

    /**
     * Issue a token when the credentials belong to an account.
     *
     * @throws InvalidCredentialsException
     */
    public function execute(StartSessionCommand $command): StartedSession
    {
        $user = $this->users->findByEmail(new Email($command->email));

        // Checked even without an account, so the response time does not tell which emails exist.
        $passwordMatches = $this->passwords->check($command->password, $user?->passwordHash);

        if ($user === null || ! $passwordMatches) {
            throw InvalidCredentialsException::create();
        }

        return new StartedSession($this->tokens->issue($user->id));
    }
}

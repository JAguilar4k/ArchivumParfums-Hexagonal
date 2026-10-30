<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Application\UseCases;

use Src\Accounts\Sessions\Application\Contracts\StaffMemberships;
use Src\Accounts\Sessions\Application\DTOs\StartedSession;
use Src\Accounts\Sessions\Application\DTOs\StartSessionCommand;
use Src\Accounts\Sessions\Domain\Contracts\TokenIssuer;
use Src\Accounts\Sessions\Domain\Exceptions\InvalidCredentialsException;
use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Accounts\Users\Domain\Contracts\PasswordHasher;
use Src\Accounts\Users\Domain\Contracts\UserRepository;
use Src\Accounts\Users\Domain\ValueObjects\Email;

final readonly class StartSession
{
    /** Receive the user repository, the password hashing, the token and the staff memberships ports. */
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwords,
        private TokenIssuer $tokens,
        private StaffMemberships $memberships,
    ) {}

    /**
     * Issue a token for the device, with the abilities of the role of the account, when the credentials belong to one.
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

        // Fixed for the life of the token: changing the role or deactivating a person revokes their tokens (#106).
        $role = $this->memberships->of($user->id)->role ?? AccountRole::Customer;

        $token = $this->tokens->issue($user->id, $command->deviceName, Ability::namesGrantedTo($role));

        return new StartedSession($token->plainTextToken, $token->expiresAt->format(DATE_ATOM));
    }
}

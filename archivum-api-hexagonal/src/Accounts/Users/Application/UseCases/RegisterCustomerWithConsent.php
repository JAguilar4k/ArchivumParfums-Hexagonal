<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Application\UseCases;

use Src\Accounts\Users\Application\DTOs\RegisterAccountCommand;
use Src\Accounts\Users\Domain\Contracts\PrivacyConsents;
use Src\Accounts\Users\Domain\Entities\User;
use Src\Accounts\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class RegisterCustomerWithConsent
{
    /** Receive the use case that opens the account, the consents port and the transaction. */
    public function __construct(
        private RegisterCustomer $registerCustomer,
        private PrivacyConsents $consents,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Open the account of a customer and keep the version of the notice accepted, both or neither (Ley 8968).
     *
     * @throws EmailAlreadyRegisteredException
     */
    public function execute(RegisterAccountCommand $command, string $noticeVersion): User
    {
        return $this->transaction->run(function () use ($command, $noticeVersion): User {
            $user = $this->registerCustomer->execute($command);
            $this->consents->record($user->id, $noticeVersion);

            return $user;
        });
    }
}

<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure\Console;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Src\Accounts\Users\Application\DTOs\RegisterAccountCommand;
use Src\Accounts\Users\Application\UseCases\RegisterPlatformAdmin;
use Src\Shared\Domain\Exceptions\DomainException;

#[Signature('archivum:create-platform-admin {name?} {email?} {--password=}')]
final class CreatePlatformAdminConsoleCommand extends Command
{
    /** Describe the command in Spanish for the command list. */
    public function __construct()
    {
        parent::__construct();

        $this->setDescription(__('accounts.create_platform_admin_description'));
    }

    /** Open the account of an administrator of the platform, answering 0 on success and 1 when a datum is refused. */
    public function handle(RegisterPlatformAdmin $registerPlatformAdmin): int
    {
        $name = $this->valueOrAsk('name', 'accounts.platform_admin_name_prompt');
        $email = $this->valueOrAsk('email', 'accounts.platform_admin_email_prompt');
        $password = $this->option('password');

        if ($password === null) {
            $password = $this->secret(__('accounts.platform_admin_password_prompt'), false);
        }

        try {
            $user = $registerPlatformAdmin->execute(new RegisterAccountCommand($name, $email, is_string($password) ? $password : ''));
        } catch (DomainException $exception) {
            $this->components->error(__($exception->translationKey(), $exception->parameters()));

            return self::FAILURE;
        }

        $this->components->info(__('accounts.platform_admin_registered', ['email' => $user->email->value]));

        return self::SUCCESS;
    }

    /** Take an argument from the call or ask for it when it is missing. */
    private function valueOrAsk(string $argument, string $question): string
    {
        $value = $this->argument($argument);

        if (! is_string($value)) {
            $value = $this->ask(__($question));
        }

        return is_string($value) ? $value : '';
    }
}

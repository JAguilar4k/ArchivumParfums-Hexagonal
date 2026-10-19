<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Infrastructure\Console;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Src\Businesses\Profile\Application\DTOs\RegisterBusinessCommand;
use Src\Businesses\Profile\Application\UseCases\RegisterBusiness;
use Src\Shared\Domain\Exceptions\DomainException;

#[Signature('archivum:create-business {name?} {slug?} {owner-name?} {owner-email?} {--password=}')]
final class CreateBusinessConsoleCommand extends Command
{
    /** Describe the command in Spanish for the command list. */
    public function __construct()
    {
        parent::__construct();

        $this->setDescription(__('businesses.create_business_description'));
    }

    /** Register the business and its owner, answering 0 on success and 1 when a datum is refused. */
    public function handle(RegisterBusiness $registerBusiness): int
    {
        $name = $this->valueOrAsk('name', 'businesses.name_prompt');
        $slug = $this->valueOrAsk('slug', 'businesses.slug_prompt');
        $ownerName = $this->valueOrAsk('owner-name', 'businesses.owner_name_prompt');
        $ownerEmail = $this->valueOrAsk('owner-email', 'businesses.owner_email_prompt');
        $password = $this->option('password');

        if ($password === null) {
            $password = $this->secret(__('businesses.owner_password_prompt'), false);
        }

        try {
            $business = $registerBusiness->execute(new RegisterBusinessCommand(
                $name,
                $slug,
                $ownerName,
                $ownerEmail,
                is_string($password) ? $password : '',
            ));
        } catch (DomainException $exception) {
            $this->components->error(__($exception->translationKey(), $exception->parameters()));

            return self::FAILURE;
        }

        $this->components->info(__('businesses.business_registered', [
            'name' => $business->name->value,
            'slug' => $business->slug->value,
            'id' => $business->id->value,
            'email' => $ownerEmail,
        ]));

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

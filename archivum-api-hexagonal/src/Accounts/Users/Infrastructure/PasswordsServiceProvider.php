<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure;

use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Support\ServiceProvider;
use Src\Accounts\Users\Domain\Contracts\PasswordResetNotifier;
use Src\Accounts\Users\Domain\Contracts\PasswordResetTokens;
use Src\Accounts\Users\Infrastructure\Mail\MailPasswordResetNotifier;
use Src\Accounts\Users\Infrastructure\Security\BrokerPasswordResetTokens;

final class PasswordsServiceProvider extends ServiceProvider
{
    /**
     * Ports of the recovery of a password bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        PasswordResetTokens::class => BrokerPasswordResetTokens::class,
        PasswordResetNotifier::class => MailPasswordResetNotifier::class,
    ];

    /**
     * Keep the new passwords exactly as typed, as a sign-in reads them. The limits of the three routes are the named
     * limiters of RateLimitsServiceProvider (#108).
     */
    public function boot(): void
    {
        TrimStrings::except(['contrasena_actual', 'contrasena_nueva']);
    }
}

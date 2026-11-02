<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Src\Accounts\Users\Domain\Contracts\PasswordResetNotifier;
use Src\Accounts\Users\Domain\Contracts\PasswordResetTokens;
use Src\Accounts\Users\Infrastructure\Mail\MailPasswordResetNotifier;
use Src\Accounts\Users\Infrastructure\Security\BrokerPasswordResetTokens;

final class PasswordsServiceProvider extends ServiceProvider
{
    public const string CHANGE = 'contrasena-cambio';

    public const string REQUEST = 'contrasena-recuperacion';

    public const string CONFIRM = 'contrasena-confirmacion';

    /**
     * Ports of the recovery of a password bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        PasswordResetTokens::class => BrokerPasswordResetTokens::class,
        PasswordResetNotifier::class => MailPasswordResetNotifier::class,
    ];

    /** Keep the new passwords exactly as typed, as a sign-in reads them, and name the limits of the three routes. */
    public function boot(): void
    {
        TrimStrings::except(['contrasena_actual', 'contrasena_nueva']);

        RateLimiter::for(self::CHANGE, fn (Request $request): Limit => $this->limit('change_by_account', 'cuenta:'.$request->user()?->getAuthIdentifier()));
        RateLimiter::for(self::REQUEST, fn (Request $request): array => $this->byIpAndEmail($request, 'request_by_ip', 'request_by_email'));
        RateLimiter::for(self::CONFIRM, fn (Request $request): array => $this->byIpAndEmail($request, 'confirm_by_ip', 'confirm_by_email'));
    }

    /**
     * A limit by IP and another by email, which reaches the cache only as a hash.
     *
     * @return list<Limit>
     */
    private function byIpAndEmail(Request $request, string $byIp, string $byEmail): array
    {
        $limits = [$this->limit($byIp, 'ip:'.$request->ip())];
        $email = $request->input('correo');

        if (is_string($email)) {
            $limits[] = $this->limit($byEmail, 'correo:'.hash('sha256', mb_strtolower(trim($email))));
        }

        return $limits;
    }

    /** Build a limit from its values in config/archivum.php. */
    private function limit(string $name, string $key): Limit
    {
        return Limit::perMinutes(
            config()->integer("archivum.password_rate_limits.{$name}.minutes"),
            config()->integer("archivum.password_rate_limits.{$name}.attempts"),
        )->by($key);
    }
}

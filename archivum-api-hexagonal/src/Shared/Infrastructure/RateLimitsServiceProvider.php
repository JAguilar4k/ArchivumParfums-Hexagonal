<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class RateLimitsServiceProvider extends ServiceProvider
{
    public const string SIGN_IN = 'sesiones';

    public const string SIGN_UP = 'cuentas';

    public const string RECOMMENDATIONS = 'recomendaciones';

    public const string WEBHOOKS = 'webhooks';

    public const string PASSWORD_CHANGE = 'contrasena-cambio';

    public const string PASSWORD_RESET = 'contrasena-recuperacion';

    public const string PASSWORD_RESET_CONFIRMATION = 'contrasena-confirmacion';

    /** Name the limiters the routes apply with the throttle middleware; each one reads its values on every request. */
    public function boot(): void
    {
        RateLimiter::for(self::SIGN_IN, fn (Request $request): array => $this->byIpAndEmail($request, 'sign_in_by_ip', 'sign_in_by_email'));

        RateLimiter::for(self::SIGN_UP, fn (Request $request): Limit => $this->limit('sign_up_by_ip', 'ip:'.$request->ip()));

        RateLimiter::for(self::RECOMMENDATIONS, function (Request $request): Limit {
            $account = $request->user('sanctum')?->getAuthIdentifier();

            return $account === null
                ? $this->limit('recommendations_by_ip', 'ip:'.$request->ip())
                : $this->limit('recommendations_by_account', 'cuenta:'.$account);
        });

        RateLimiter::for(self::WEBHOOKS, fn (Request $request): Limit => $this->limit('webhooks_by_ip', 'ip:'.$request->ip()));

        // The change runs after auth:sanctum, so the account is always known.
        RateLimiter::for(self::PASSWORD_CHANGE, fn (Request $request): Limit => $this->limit('password_change_by_account', 'cuenta:'.$request->user()?->getAuthIdentifier()));

        RateLimiter::for(self::PASSWORD_RESET, fn (Request $request): array => $this->byIpAndEmail($request, 'password_reset_by_ip', 'password_reset_by_email'));

        RateLimiter::for(self::PASSWORD_RESET_CONFIRMATION, fn (Request $request): array => $this->byIpAndEmail($request, 'password_reset_confirmation_by_ip', 'password_reset_confirmation_by_email'));
    }

    /**
     * The limit by IP stops one machine; the limit by email stops the guessing of one password, or of one recovery
     * token, from many machines. The email reaches the cache only as a hash, because the cache must not hold personal
     * data.
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
            config()->integer("archivum.rate_limits.{$name}.minutes"),
            config()->integer("archivum.rate_limits.{$name}.attempts"),
        )->by($key);
    }
}

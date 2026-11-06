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

    /** Name the limiters the routes apply with the throttle middleware; each one reads its values on every request. */
    public function boot(): void
    {
        RateLimiter::for(self::SIGN_IN, fn (Request $request): array => $this->signInLimits($request));

        RateLimiter::for(self::SIGN_UP, fn (Request $request): Limit => $this->limit('sign_up_by_ip', 'ip:'.$request->ip()));

        RateLimiter::for(self::RECOMMENDATIONS, function (Request $request): Limit {
            $account = $request->user('sanctum')?->getAuthIdentifier();

            return $account === null
                ? $this->limit('recommendations_by_ip', 'ip:'.$request->ip())
                : $this->limit('recommendations_by_account', 'cuenta:'.$account);
        });

        RateLimiter::for(self::WEBHOOKS, fn (Request $request): Limit => $this->limit('webhooks_by_ip', 'ip:'.$request->ip()));
    }

    /**
     * The limit by IP stops one machine; the limit by email stops the guessing of one password from many machines.
     * The email reaches the cache only as a hash, because the cache must not hold personal data.
     *
     * @return list<Limit>
     */
    private function signInLimits(Request $request): array
    {
        $limits = [$this->limit('sign_in_by_ip', 'ip:'.$request->ip())];
        $email = $request->input('correo');

        if (is_string($email)) {
            $limits[] = $this->limit('sign_in_by_email', 'correo:'.hash('sha256', mb_strtolower(trim($email))));
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

<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RedirectToHttps
{
    /**
     * In production, answer a plain HTTP request with a redirect to its address on HTTPS. The 308 keeps the method and
     * the body, and the host comes from APP_URL, never from the request. The health check of the platform stays out.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction() && ! $request->secure() && ! $request->is('up')) {
            $origin = (string) preg_replace('#^http://#', 'https://', rtrim(config()->string('app.url'), '/'));

            return new RedirectResponse($origin.$request->getRequestUri(), Response::HTTP_PERMANENTLY_REDIRECT);
        }

        return $next($request);
    }
}

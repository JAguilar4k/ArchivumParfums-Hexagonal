<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetSecurityHeaders
{
    private const string API_CONTENT_POLICY = "default-src 'none'; frame-ancestors 'none'";

    /** The API documentation loads its viewer from unpkg, so it needs a policy of its own. */
    private const string DOCS_CONTENT_POLICY = "default-src 'self'; script-src 'self' 'unsafe-inline' https://unpkg.com; style-src 'self' 'unsafe-inline' https://unpkg.com; img-src 'self' data: https:; font-src 'self' data: https:; connect-src 'self'; frame-ancestors 'none'";

    /** Add the security headers to every response and take away the one that names the language of the server. */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('Content-Security-Policy', $request->is('docs/*') ? self::DOCS_CONTENT_POLICY : self::API_CONTENT_POLICY);
        $headers->remove('X-Powered-By');
        header_remove('X-Powered-By');

        // Only in production, where everything goes through HTTPS: a browser remembers this header.
        if (app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}

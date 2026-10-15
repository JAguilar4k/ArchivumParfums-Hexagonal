<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final readonly class AssignRequestId
{
    public const string HEADER = 'X-Request-Id';

    public const string CONTEXT_KEY = 'request_id';

    /** Tag the request, its log entries and its response with one identifier. */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolve($request);

        Context::add(self::CONTEXT_KEY, $requestId);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }

    /** Reuse a well-formed incoming identifier or generate a new one. */
    private function resolve(Request $request): string
    {
        $incoming = $request->headers->get(self::HEADER);

        return is_string($incoming) && Str::isUuid($incoming)
            ? Str::lower($incoming)
            : (string) Str::uuid7();
    }
}

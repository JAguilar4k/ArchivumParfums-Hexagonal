<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Http\Problem;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\Exceptions\NotFoundException;
use Src\Shared\Infrastructure\Http\Middleware\AssignRequestId;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final readonly class ProblemDetailsRenderer
{
    public const string CONTENT_TYPE = 'application/problem+json';

    /** Render any exception as an RFC 9457 problem document. */
    public function __invoke(Throwable $exception): JsonResponse
    {
        return match (true) {
            $exception instanceof ValidationException => $this->respond(
                ProblemType::InvalidData,
                extensions: ['errores' => $exception->errors()],
            ),
            $exception instanceof DomainException => $this->respond(
                $this->typeOfDomainException($exception),
                __($exception->translationKey(), $exception->parameters()),
            ),
            $exception instanceof AuthenticationException => $this->respond(ProblemType::Unauthenticated),
            $exception instanceof HttpExceptionInterface => $this->respond(
                ProblemType::fromStatus($exception->getStatusCode()),
                headers: $exception->getHeaders(),
            ),
            default => $this->respond(ProblemType::InternalError),
        };
    }

    /** Classify a domain exception into a problem type. */
    private function typeOfDomainException(DomainException $exception): ProblemType
    {
        return match (true) {
            $exception instanceof NotFoundException => ProblemType::NotFound,
            $exception instanceof InvalidValueException => ProblemType::InvalidData,
            default => ProblemType::Conflict,
        };
    }

    /**
     * Build the problem document for a type.
     *
     * @param  array<string, mixed>  $extensions
     * @param  array<string, mixed>  $headers
     */
    private function respond(
        ProblemType $type,
        ?string $detail = null,
        array $extensions = [],
        array $headers = [],
    ): JsonResponse {
        return new JsonResponse(
            [
                'type' => rtrim((string) config('app.url'), '/').'/problemas/'.$type->value,
                'title' => __("problems.{$type->value}.title"),
                'status' => $type->status(),
                'detail' => $detail ?? __("problems.{$type->value}.detail"),
                'instance' => 'urn:uuid:'.Context::get(AssignRequestId::CONTEXT_KEY),
                ...$extensions,
            ],
            $type->status(),
            [...$headers, 'Content-Type' => self::CONTENT_TYPE],
        );
    }
}

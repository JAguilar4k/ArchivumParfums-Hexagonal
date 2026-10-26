<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\OpenApi;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Src\Shared\Infrastructure\Http\Problem\ProblemDetailsRenderer;
use Src\Shared\Infrastructure\Http\Problem\ProblemType;

/** Document the errors of every operation as RFC 9457 problems, the way ProblemDetailsRenderer answers them. */
final class ProblemResponses implements DocumentTransformer
{
    private const string PROBLEM = 'Problema';

    private const string VALIDATION_PROBLEM = 'ProblemaDeValidacion';

    private const string LOCALE = 'es';

    /** Replace the errors Scramble guessed with the problems the API really answers. */
    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        $problem = $document->components->addSchema(self::PROBLEM, Schema::fromType($this->problemType()));
        $validationProblem = $document->components->addSchema(
            self::VALIDATION_PROBLEM,
            Schema::fromType($this->validationProblemType()),
        );

        foreach ($document->paths as $path) {
            foreach ($path->operations as $operation) {
                $operation->responses = [
                    ...$this->successfulResponses($operation),
                    ...$this->problemResponses($path->path, $operation, $problem, $validationProblem),
                ];
            }
        }
    }

    /**
     * Keep what Scramble inferred for the successful responses.
     *
     * @return list<Response|Reference>
     */
    private function successfulResponses(Operation $operation): array
    {
        return array_values(array_filter(
            $operation->responses ?? [],
            fn (Response|Reference $response): bool => $this->codeOf($response) < 400,
        ));
    }

    /**
     * Build the problems an operation can answer, from the lowest status to the highest.
     *
     * @return list<Response>
     */
    private function problemResponses(string $path, Operation $operation, Reference $problem, Reference $validationProblem): array
    {
        $types = [ProblemType::InternalError];

        if ($operation->security !== null && $operation->security !== []) {
            $types[] = ProblemType::Unauthenticated;
        }

        if ($this->isPanelRoute($path)) {
            $types[] = ProblemType::Unauthenticated;
            $types[] = ProblemType::Forbidden;
        }

        if ($this->isOrderOrPaymentRoute($path)) {
            $types[] = ProblemType::Forbidden;
            $types[] = ProblemType::Conflict;
            $types[] = ProblemType::InvalidData;
        }

        if ($this->rejectsCredentials($path, $operation)) {
            $types[] = ProblemType::InvalidCredentials;
        }

        if (str_contains($path, '{')) {
            $types[] = ProblemType::NotFound;
        }

        if ($this->receivesInput($operation)) {
            $types[] = ProblemType::InvalidData;
        }

        $types = array_unique($types, SORT_REGULAR);
        usort($types, fn (ProblemType $a, ProblemType $b): int => $a->status() <=> $b->status());

        return array_map(
            fn (ProblemType $type): Response => $this->response(
                $type,
                $type === ProblemType::InvalidData ? $validationProblem : $problem,
            ),
            $types,
        );
    }

    /** One problem response, with the title of the type as its description. */
    private function response(ProblemType $type, Reference $schema): Response
    {
        return Response::make($type->status())
            ->setDescription(__("problems.{$type->value}.title", [], self::LOCALE))
            ->setContent(ProblemDetailsRenderer::CONTENT_TYPE, $schema);
    }

    /** Whether the operation validates a body or a query string. */
    private function receivesInput(Operation $operation): bool
    {
        return $operation->requestBodyObject !== null
            || array_any(
                $operation->parameters,
                fn (Parameter|Reference $parameter): bool => $parameter instanceof Parameter && $parameter->in === 'query',
            );
    }

    /** Signing in is public, yet it answers 401 when the email or the password is wrong, with no Sanctum token involved. */
    private function rejectsCredentials(string $path, Operation $operation): bool
    {
        return ltrim($path, '/') === 'sesiones' && strtolower($operation->method) === 'post';
    }

    /** The panel is the part of the API that checks the role of the member. */
    private function isPanelRoute(string $path): bool
    {
        return str_starts_with(ltrim($path, '/'), 'panel');
    }

    /** These operations also check the role, ownership and the current business state. */
    private function isOrderOrPaymentRoute(string $path): bool
    {
        $path = ltrim($path, '/');

        return str_starts_with($path, 'cuenta/pedidos') || str_starts_with($path, 'cuenta/pagos')
            || str_starts_with($path, 'panel/pedidos') || str_starts_with($path, 'panel/botellas')
            || $path === 'tiendas/{tienda}/pedidos';
    }

    /** HTTP status of a response, resolving the references to the components. */
    private function codeOf(Response|Reference $response): int
    {
        $resolved = $response instanceof Reference ? $response->resolve() : $response;

        return (int) $resolved->code;
    }

    /** The members every problem document carries (RFC 9457). */
    private function problemType(): ObjectType
    {
        return new ObjectType()
            ->addProperty('type', new StringType()->format('uri')->setDescription('URL del tipo de problema.'))
            ->addProperty('title', new StringType()->setDescription('Resumen corto del problema.'))
            ->addProperty('status', new IntegerType()->setDescription('Estado HTTP de la respuesta.'))
            ->addProperty('detail', new StringType()->setDescription('Explicación del problema para quien usa la API.'))
            ->addProperty('instance', new StringType()
                ->setDescription('Identifica esta ocurrencia: es el `X-Request-Id` como `urn:uuid:<id>`.'))
            ->setRequired(['type', 'title', 'status', 'detail', 'instance']);
    }

    /** The problem of a failed validation, which adds the messages of each field. */
    private function validationProblemType(): ObjectType
    {
        $messages = new ArrayType()->setItems(new StringType);

        return $this->problemType()
            ->addProperty('errores', new ObjectType()
                ->additionalProperties($messages)
                ->setDescription('Mensajes de error por campo.'))
            ->addRequired(['errores']);
    }
}

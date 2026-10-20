<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\OpenApi;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Foundation\Http\FormRequest;
use ReflectionNamedType;

/**
 * Keep optional the fields that the form request declares with `sometimes`.
 *
 * Scramble makes an object required as soon as one of its nested rules is `required`, even when the object itself is
 * `sometimes`, so a partial update would oblige clients to send what the server accepts without.
 */
final class OptionalFields
{
    /** Remove from the request body the required fields whose rules start with `sometimes`. */
    public static function document(Operation $operation, RouteInfo $routeInfo): void
    {
        $request = self::formRequest($routeInfo);
        $body = $operation->requestBodyObject;
        $schema = $body instanceof RequestBodyObject ? ($body->content['application/json'] ?? null) : null;

        if ($request === null || $schema === null) {
            return;
        }

        $type = ($schema instanceof Reference ? $schema->resolve() : $schema)?->type;

        $callback = [$request, 'rules'];

        if (! $type instanceof ObjectType || ! is_callable($callback)) {
            return;
        }

        $optional = [];

        /** @var array<string, mixed> $rules */
        $rules = app()->call($callback);

        foreach ($rules as $field => $fieldRules) {
            $list = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;

            if (! str_contains((string) $field, '.') && is_array($list) && in_array('sometimes', $list, true)) {
                $optional[] = $field;
            }
        }

        $type->required = array_values(array_diff($type->required, $optional));
    }

    /** The form request the action receives, if any. */
    private static function formRequest(RouteInfo $routeInfo): ?FormRequest
    {
        foreach ($routeInfo->reflectionMethod()?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && is_subclass_of($type->getName(), FormRequest::class)) {
                // Built by hand: resolving it through the container would validate an empty request.
                return new ($type->getName());
            }
        }

        return null;
    }
}

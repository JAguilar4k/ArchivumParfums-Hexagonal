<?php

declare(strict_types=1);

/**
 * Every class that lives in an Application/UseCases folder under src/.
 *
 * @return array<string, array{ReflectionClass<object>}>
 */
function useCaseClasses(): array
{
    $root = dirname(__DIR__, 2);
    $classes = [];

    foreach (glob("{$root}/src/{*,*/*}/Application/UseCases/*.php", GLOB_BRACE) ?: [] as $path) {
        $class = 'Src\\'.str_replace('/', '\\', substr($path, strlen($root) + 5, -4));
        if (class_exists($class)) {
            $classes[$class] = [new ReflectionClass($class)];
        }
    }

    return $classes;
}

$description = 'gives every use case a single public method besides the constructor';

if (useCaseClasses() === []) {
    it($description)->skip('No use cases exist yet.');
} else {
    it($description, function (ReflectionClass $class) {
        $methods = array_filter(
            $class->getMethods(ReflectionMethod::IS_PUBLIC),
            fn (ReflectionMethod $method): bool => ! $method->isConstructor(),
        );

        expect($methods)->toHaveCount(1);
    })->with(useCaseClasses());
}

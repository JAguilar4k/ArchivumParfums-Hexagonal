<?php

declare(strict_types=1);

/**
 * Every class that lives in an Application/UseCases folder under src/.
 *
 * @return array<string, array{class-string}>
 */
function useCaseClasses(): array
{
    $root = dirname(__DIR__, 2);
    $classes = [];

    foreach (glob("{$root}/src/{*,*/*}/Application/UseCases/*.php", GLOB_BRACE) ?: [] as $path) {
        $class = 'Src\\'.str_replace('/', '\\', substr($path, strlen($root) + 5, -4));
        $classes[$class] = [$class];
    }

    return $classes;
}

it('gives every use case a single public method besides the constructor', function (?string $class) {
    if ($class === null) {
        $this->markTestSkipped('No use cases exist yet.');
    }

    $methods = array_filter(
        new ReflectionClass($class)->getMethods(ReflectionMethod::IS_PUBLIC),
        fn (ReflectionMethod $method): bool => ! $method->isConstructor(),
    );

    expect($methods)->toHaveCount(1);
})->with(useCaseClasses() ?: ['no use cases yet' => [null]]);

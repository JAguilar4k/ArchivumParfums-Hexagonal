<?php

declare(strict_types=1);

/**
 * Namespaces of every layer of a given name under src/, such as Src\Shared\Domain.
 *
 * @return list<string>
 */
function layerNamespaces(string $layer): array
{
    $paths = [
        ...glob(dirname(__DIR__, 2)."/src/*/{$layer}", GLOB_ONLYDIR) ?: [],
        ...glob(dirname(__DIR__, 2)."/src/*/*/{$layer}", GLOB_ONLYDIR) ?: [],
    ];

    return array_map(
        fn (string $path): string => 'Src\\'.str_replace('/', '\\', substr($path, strlen(dirname(__DIR__, 2)) + 5)),
        $paths,
    );
}

/**
 * The framework: Illuminate and the namespace of every Laravel package installed, such as Laravel\Ai or
 * Laravel\Sanctum. Pest finds a namespace through the autoloader, and no package maps the bare Laravel\ prefix,
 * so the name Laravel alone would match nothing.
 *
 * @return list<string>
 */
function frameworkNamespaces(): array
{
    /** @var array<string, list<string>> $autoload */
    $autoload = require dirname(__DIR__, 2).'/vendor/composer/autoload_psr4.php';
    $laravel = array_filter(array_keys($autoload), fn (string $prefix): bool => str_starts_with($prefix, 'Laravel\\'));

    return ['Illuminate', ...array_map(fn (string $prefix): string => rtrim($prefix, '\\'), array_values($laravel))];
}

/**
 * Root namespaces of the modules a domain must not know, given the domain namespace.
 *
 * @return list<string>
 */
function foreignModules(string $domain): array
{
    $parts = explode('\\', $domain);
    $own = $parts[1] === 'Shared' ? 'Src\\Shared' : "Src\\{$parts[1]}\\{$parts[2]}";

    return array_values(array_filter(
        array_map(fn (string $namespace): string => substr($namespace, 0, -strlen('\\Domain')), layerNamespaces('Domain')),
        fn (string $module): bool => $module !== $own && ! str_ends_with($module, '\\Shared') && $module !== 'Src\\Shared',
    ));
}

foreach (layerNamespaces('Domain') as $domain) {
    arch("the domain {$domain} does not depend on the framework", function () use ($domain) {
        expect($domain)->not->toUse(frameworkNamespaces());
    });

    $outer = [...layerNamespaces('Application'), ...layerNamespaces('Infrastructure')];

    if ($outer !== []) {
        arch("the domain {$domain} does not depend on application or infrastructure code", function () use ($domain, $outer) {
            expect($domain)->not->toUse($outer);
        });
    }

    $foreign = foreignModules($domain);

    if ($foreign !== []) {
        arch("the domain {$domain} only knows its module and the shared kernels", function () use ($domain, $foreign) {
            expect($domain)->not->toUse($foreign);
        });
    }
}

foreach (layerNamespaces('Application') as $application) {
    arch("the application layer {$application} does not depend on the framework", function () use ($application) {
        expect($application)->not->toUse(frameworkNamespaces());
    });

    $infrastructure = layerNamespaces('Infrastructure');

    if ($infrastructure !== []) {
        arch("the application layer {$application} does not depend on infrastructure code", function () use ($application, $infrastructure) {
            expect($application)->not->toUse($infrastructure);
        });
    }
}

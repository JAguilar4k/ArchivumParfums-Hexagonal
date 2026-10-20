<?php

declare(strict_types=1);

/** Variables of .env.example that may carry a value although their name looks secret: the fictitious demo accounts. */
const EXAMPLE_VALUES_ALLOWED = ['ARCHIVUM_DEMO_PASSWORD'];

/**
 * Names of the variables requested through env() in the PHP files under a directory of the application.
 *
 * @return list<string>
 */
function environmentVariablesReadIn(string $directory): array
{
    $variables = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2)."/{$directory}"));

    foreach ($files as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            preg_match_all('/\benv\(\s*[\'"]([A-Z0-9_]+)[\'"]/', (string) file_get_contents($file->getPathname()), $matches);
            $variables = [...$variables, ...$matches[1]];
        }
    }

    return array_values(array_unique($variables));
}

/**
 * Variables of .env.example with their example value; a commented line documents a variable without setting it.
 *
 * @return array<string, string>
 */
function exampleEnvironmentVariables(): array
{
    preg_match_all('/^(#?)\s*([A-Z][A-Z0-9_]*)=(.*)$/m', (string) file_get_contents(dirname(__DIR__, 2).'/.env.example'), $lines, PREG_SET_ORDER);

    $variables = [];

    foreach ($lines as [, $comment, $name, $value]) {
        $variables[$name] = $comment === '#' ? '' : trim($value);
    }

    return $variables;
}

it('lists in .env.example every variable that config/ reads', function () {
    $undocumented = array_diff(environmentVariablesReadIn('config'), array_keys(exampleEnvironmentVariables()));

    expect(array_values($undocumented))->toBe([]);
});

it('reads the environment only from config/', function (string $directory) {
    expect(environmentVariablesReadIn($directory))->toBe([], "env() is called under {$directory}/; read the value with config() instead.");
})->with(['app', 'bootstrap', 'database', 'lang', 'routes', 'src']);

it('leaves every key, secret, token and password of .env.example empty', function () {
    $filled = array_filter(
        exampleEnvironmentVariables(),
        fn (string $value, string $name): bool => preg_match('/KEY|SECRET|TOKEN|PASSWORD/', $name) === 1
            && $value !== ''
            && ! in_array($name, EXAMPLE_VALUES_ALLOWED, true),
        ARRAY_FILTER_USE_BOTH,
    );

    expect($filled)->toBe([]);
});

it('keeps the real environment file out of git', function () {
    expect(explode("\n", (string) file_get_contents(dirname(__DIR__, 2).'/.gitignore')))->toContain('.env');
});

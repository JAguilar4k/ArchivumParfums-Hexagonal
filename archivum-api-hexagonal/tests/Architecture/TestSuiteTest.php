<?php

declare(strict_types=1);

/**
 * Every PHP file under tests/Unit, keyed by its path inside tests/.
 *
 * @return array<string, array{string}>
 */
function unitTestFiles(): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/Unit', FilesystemIterator::SKIP_DOTS)) as $file) {
        /** @var SplFileInfo $file */
        if ($file->getExtension() === 'php') {
            $files[substr($file->getPathname(), strlen(dirname(__DIR__)) + 1)] = [$file->getPathname()];
        }
    }

    ksort($files);

    return $files;
}

it('tests the domain and the use cases without Laravel or the infrastructure', function (string $path) {
    preg_match_all('/^use (?:function )?([^;]+);/m', (string) file_get_contents($path), $matches);

    $framework = array_filter(
        $matches[1],
        fn (string $import): bool => preg_match('/^(Illuminate|Laravel|Pest\\\\Laravel|Tests\\\\TestCase)\b|\\\\Infrastructure\\\\/', $import) === 1,
    );

    expect(array_values($framework))->toBe([]);
})->with(unitTestFiles());

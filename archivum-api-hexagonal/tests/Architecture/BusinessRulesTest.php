<?php

declare(strict_types=1);

/**
 * Identifiers of the business rules in section 5 of docs/especificacion.md, in order.
 *
 * @return list<string>
 */
function businessRules(): array
{
    $path = dirname(__DIR__, 3).'/docs/especificacion.md';

    if (! is_file($path)) {
        throw new RuntimeException("The specification is missing at {$path}.");
    }

    preg_match_all('/^- \*\*(R\d+)\.\*\*/m', (string) file_get_contents($path), $matches);

    return $matches[1];
}

/**
 * Rules that no test names yet, from pending-rules.php.
 *
 * @return array<string, string>
 */
function pendingRules(): array
{
    /** @var array<string, string> */
    return require __DIR__.'/pending-rules.php';
}

/**
 * Rules named at the start of a test description in any file under tests/, other than this one and the pending list.
 *
 * @return list<string>
 */
function rulesNamedInTests(): array
{
    $skipped = [__FILE__, __DIR__.'/pending-rules.php'];
    $named = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__), FilesystemIterator::SKIP_DOTS)) as $file) {
        /** @var SplFileInfo $file */
        if ($file->getExtension() !== 'php' || in_array($file->getPathname(), $skipped, true)) {
            continue;
        }

        preg_match_all('/[\'"](R\d+): /', (string) file_get_contents($file->getPathname()), $matches);
        array_push($named, ...$matches[1]);
    }

    return array_values(array_unique($named));
}

it('reads the rules of the specification from R1 on, without gaps', function () {
    $rules = businessRules();

    expect($rules)->not->toBeEmpty()
        ->and($rules)->toBe(array_map(fn (int $number): string => "R{$number}", range(1, count($rules))));
});

it('names every business rule in a test unless it is pending', function () {
    $missing = array_diff(businessRules(), rulesNamedInTests(), array_keys(pendingRules()));

    expect(array_values($missing))->toBe([], 'Name these rules in a test, as in it(\'R14: ...\'), or list them in pending-rules.php.');
});

it('keeps pending only the rules that no test names yet', function () {
    $named = array_intersect(array_keys(pendingRules()), rulesNamedInTests());

    expect(array_values($named))->toBe([], 'A test names these rules: remove them from pending-rules.php.');
});

it('keeps pending only rules of the specification', function () {
    $unknown = array_diff(array_keys(pendingRules()), businessRules());

    expect(array_values($unknown))->toBe([], 'These rules are not in section 5 of docs/especificacion.md.');
});

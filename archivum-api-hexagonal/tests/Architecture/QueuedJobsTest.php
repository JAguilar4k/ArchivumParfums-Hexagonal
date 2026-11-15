<?php

declare(strict_types=1);

use Src\Payments\Payments\Infrastructure\Jobs\ProcessPaymentEventJob;
use Src\Shared\Infrastructure\Queue\IdempotentJob;

/**
 * Every class that lives in an Infrastructure/Jobs folder under src/, as in the guide repository.
 *
 * @return array<string, array{ReflectionClass<object>}>
 */
function queuedJobClasses(): array
{
    $root = dirname(__DIR__, 2);
    $classes = [];

    foreach (glob("{$root}/src/*/*/Infrastructure/Jobs/*.php") ?: [] as $path) {
        $class = 'Src\\'.str_replace('/', '\\', substr($path, strlen($root) + 5, -4));
        if (class_exists($class)) {
            $classes[basename($path, '.php')] = [new ReflectionClass($class)];
        }
    }

    return $classes;
}

it('gives every job a key and the attempts and waits of config/archivum.php through IdempotentJob', function (ReflectionClass $class): void {
    expect($class->isSubclassOf(IdempotentJob::class))->toBeTrue()
        ->and($class->hasProperty('tries') || $class->hasProperty('backoff'))->toBeFalse()
        ->and($class->getMethod('tries')->getDeclaringClass()->getName())->toBe(IdempotentJob::class)
        ->and($class->getMethod('backoff')->getDeclaringClass()->getName())->toBe(IdempotentJob::class);
})->with(queuedJobClasses());

it('runs every job through ScopeJobToBusiness, except the job of the webhook, whose business comes from its payment', function (ReflectionClass $class): void {
    $scoped = $class->hasMethod('middleware')
        && str_contains((string) file_get_contents((string) $class->getFileName()), 'new ScopeJobToBusiness(');

    expect($scoped)->toBe($class->getName() !== ProcessPaymentEventJob::class);
})->with(queuedJobClasses());

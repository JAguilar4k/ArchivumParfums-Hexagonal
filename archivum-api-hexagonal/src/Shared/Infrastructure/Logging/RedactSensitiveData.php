<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Logging;

use Illuminate\Log\Logger;
use Monolog\Logger as Monolog;
use Monolog\LogRecord;
use Throwable;

/**
 * Keep passwords, tokens, emails and phones out of every log entry, wherever they come from. A failed query, for one,
 * writes its bindings into the message of its exception. Configured as the tap of each channel in config/logging.php.
 */
final class RedactSensitiveData
{
    /** @var array<string, string> */
    private const array PATTERNS = [
        '/\$2[aby]\$\d{2}\$[.\/A-Za-z0-9]{53}/' => '[contraseña]',
        '/\b\d+\|[A-Za-z0-9]{40,}\b/' => '[token]',
        '/\bBearer\s+\S+/i' => '[token]',
        '/[\w.+-]+@[\w-]+(\.[\w-]+)+/' => '[correo]',
        '/\+\d{1,3}[\s-]?\d{3,4}[\s-]?\d{4}\b/' => '[teléfono]',
        '/\+\d{8,15}\b/' => '[teléfono]',
        // A local number, with or without the country code; the lookarounds keep the groups of a UUID out.
        '/(?<![\w.-])(?:506[\s-]?)?[245678]\d{3}[\s-]?\d{4}(?![\w.-])/' => '[teléfono]',
    ];

    /** Names of the fields whose whole value leaves the log. */
    private const array SECRET_KEYS = ['password', 'contrasena', 'token', 'authorization', 'phone', 'telefono', 'correo', 'email'];

    /** Add the redaction to the logger of the channel. */
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();
        assert($monolog instanceof Monolog);

        $monolog->pushProcessor($this->redact(...));
    }

    /** Redact the message, the context and the extra data of an entry. */
    public function redact(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->clean($record->message),
            context: $this->cleanArray($record->context),
            extra: $this->cleanArray($record->extra),
        );
    }

    /**
     * Redact every value of an array, at any depth, and read an exception as its text with its stack trace.
     *
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private function cleanArray(array $values): array
    {
        $cleaned = [];

        foreach ($values as $key => $value) {
            $cleaned[$key] = match (true) {
                is_string($key) && in_array(strtolower($key), self::SECRET_KEYS, true) => '[oculto]',
                is_array($value) => $this->cleanArray($value),
                is_string($value) => $this->clean($value),
                $value instanceof Throwable => $this->describe($value),
                default => $value,
            };
        }

        return $cleaned;
    }

    /**
     * The exception and the ones it wraps, each with its stack trace, as the log needs them to debug. The trace leaves
     * out the arguments of each call, the only place of a trace where a password or an email would travel.
     */
    private function describe(Throwable $exception): string
    {
        $parts = [];

        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            $parts[] = $current::class.': '.$current->getMessage().' at '.$current->getFile().':'.$current->getLine()."\n".$this->traceOf($current);
        }

        return $this->clean(implode("\nCaused by: ", $parts));
    }

    /** The stack trace of an exception, one call per line, without arguments. */
    private function traceOf(Throwable $exception): string
    {
        $lines = [];

        foreach ($exception->getTrace() as $index => $frame) {
            $place = isset($frame['file']) ? $frame['file'].'('.($frame['line'] ?? 0).')' : '[internal function]';
            $lines[] = "#{$index} {$place}: ".($frame['class'] ?? '').($frame['type'] ?? '').$frame['function'].'()';
        }

        return implode("\n", $lines);
    }

    /** Replace what looks like a secret or personal data in a text. */
    private function clean(string $text): string
    {
        return (string) preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $text);
    }
}

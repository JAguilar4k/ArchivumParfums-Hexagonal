<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Logging;

use Illuminate\Log\Logger;
use Monolog\Logger as Monolog;
use Monolog\LogRecord;

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
        '/\+\d{8,15}\b/' => '[teléfono]',
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
     * Redact every value of an array, at any depth, and read an exception as its text.
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
                $value instanceof \Throwable => $this->clean($value::class.': '.$value->getMessage().' at '.$value->getFile().':'.$value->getLine()),
                default => $value,
            };
        }

        return $cleaned;
    }

    /** Replace what looks like a secret or personal data in a text. */
    private function clean(string $text): string
    {
        return (string) preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $text);
    }
}

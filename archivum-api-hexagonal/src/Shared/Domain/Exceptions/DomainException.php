<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exceptions;

use DomainException as BaseDomainException;

abstract class DomainException extends BaseDomainException
{
    /**
     * Build the exception from a translation key and its placeholders.
     *
     * @param  array<string, int|string>  $parameters
     */
    final public function __construct(
        private readonly string $translationKey,
        private readonly array $parameters = [],
    ) {
        parent::__construct($translationKey);
    }

    /** Translation key of the user-facing message. */
    final public function translationKey(): string
    {
        return $this->translationKey;
    }

    /**
     * Placeholders for the user-facing message.
     *
     * @return array<string, int|string>
     */
    final public function parameters(): array
    {
        return $this->parameters;
    }
}

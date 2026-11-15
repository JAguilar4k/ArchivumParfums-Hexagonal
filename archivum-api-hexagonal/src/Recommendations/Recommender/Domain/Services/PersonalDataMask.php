<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Domain\Services;

final readonly class PersonalDataMask
{
    /**
     * The contact data a customer may write in the text and what replaces each one: an email, a phone with its
     * country code and a local number of Costa Rica, with or without 506, spaces or a hyphen. They are the same
     * patterns the logs redact (#110), except that a phone may end a sentence. A price in colones is not a phone: it
     * does not have eight digits.
     */
    private const array PATTERNS = [
        '/[\w.+-]+@[\w-]+(\.[\w-]+)+/u' => '[correo]',
        '/\+\d{1,3}[\s-]?\d{3,4}[\s-]?\d{4}\b/u' => '[teléfono]',
        '/\+\d{8,15}\b/u' => '[teléfono]',
        // The lookarounds keep a longer number, such as an amount or the group of an identifier, out; a period that
        // ends the sentence may follow the phone.
        '/(?<![\w.-])(?:506[\s-]?)?[245678]\d{3}[\s-]?\d{4}(?![\w-]|\.\w)/u' => '[teléfono]',
    ];

    /**
     * The customer's text without the emails and phones it carries, so they never reach a recommender (R51). The
     * rest of the text stays as the customer wrote it.
     */
    public function apply(string $text): string
    {
        return (string) preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $text);
    }
}

<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Http;

/** The words the API uses for the moments and climates, paired with the values the database stores. */
final class PerfumeVocabulary
{
    /**
     * Moments of the day by their word in the API.
     *
     * @var array<string, string>
     */
    public const array MOMENTS = [
        'dia' => 'day',
        'tarde' => 'afternoon',
        'noche' => 'night',
    ];

    /**
     * Climates by their word in the API.
     *
     * @var array<string, string>
     */
    public const array CLIMATES = [
        'calido' => 'warm',
        'templado' => 'temperate',
        'frio' => 'cold',
    ];

    /** Word of the API for a stored moment of the day. */
    public static function momentOf(string $stored): string
    {
        return (string) array_search($stored, self::MOMENTS, true);
    }

    /** Word of the API for a stored climate. */
    public static function climateOf(string $stored): string
    {
        return (string) array_search($stored, self::CLIMATES, true);
    }
}

<?php

declare(strict_types=1);

namespace Src\Shared\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

/** The image types the store accepts, recognized by the content of the file and never by its extension. */
enum ImageType: string
{
    case Png = 'image/png';
    case Jpeg = 'image/jpeg';
    case Webp = 'image/webp';

    /** Largest size of an image in kilobytes (R53). */
    public const int MAX_KILOBYTES = 2048;

    /**
     * The mime types as the validation rules list them.
     *
     * @return list<string>
     */
    public static function mimeTypes(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }

    /** The type of a mime type detected in the content of a file; any other is refused. */
    public static function fromMime(string $mime): self
    {
        return self::tryFrom($mime) ?? throw new InvalidValueException('files.image_type_invalid');
    }

    /** Extension that the file is stored with. */
    public function extension(): string
    {
        return match ($this) {
            self::Png => 'png',
            self::Jpeg => 'jpg',
            self::Webp => 'webp',
        };
    }
}

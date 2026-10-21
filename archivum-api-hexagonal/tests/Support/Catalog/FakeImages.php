<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use Illuminate\Http\UploadedFile;

/**
 * Uploaded files with the real content of an image or of another type, written to a temporary file. UploadedFile::fake()
 * guesses the type from the name; these do not, so the tests prove that the type is read from the content and never
 * from the name. They do not need the GD extension.
 */
final class FakeImages
{
    /** A PNG of one pixel. */
    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /** A one-pixel GIF: an image, but not one of the accepted types. */
    private const string GIF = 'R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==';

    /** A real PNG under the name given. */
    public static function png(string $name = 'mi-logo.png'): UploadedFile
    {
        return self::upload($name, (string) base64_decode(self::PNG, true));
    }

    /** A real PNG followed by enough bytes to weigh the kilobytes given, to test the limit. */
    public static function pngOfKilobytes(int $kilobytes): UploadedFile
    {
        return self::upload('grande.png', (string) base64_decode(self::PNG, true).str_repeat("\0", $kilobytes * 1024));
    }

    /** A PDF that is named like a PNG. */
    public static function pdfNamedPng(): UploadedFile
    {
        return self::upload('falso.png', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
    }

    /** A GIF named like a PNG: an image, but of a type the store does not accept. */
    public static function gif(): UploadedFile
    {
        return self::upload('animada.png', (string) base64_decode(self::GIF, true));
    }

    /** Write the content to a temporary file and upload it under the name the client announces. */
    private static function upload(string $name, string $content): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'archivum-');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }
}

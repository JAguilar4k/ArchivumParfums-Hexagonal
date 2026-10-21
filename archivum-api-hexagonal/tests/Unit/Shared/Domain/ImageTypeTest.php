<?php

declare(strict_types=1);

use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\ImageType;

it('maps each accepted mime type to the extension the file is stored with', function (string $mime, string $extension) {
    expect(ImageType::fromMime($mime)->extension())->toBe($extension);
})->with([
    'png' => ['image/png', 'png'],
    'jpeg' => ['image/jpeg', 'jpg'],
    'webp' => ['image/webp', 'webp'],
]);

it('refuses any other type', function (string $mime) {
    ImageType::fromMime($mime);
})->with(['image/gif', 'application/pdf', 'text/plain', 'image/svg+xml', ''])
    ->throws(InvalidValueException::class, 'files.image_type_invalid');

it('lists the three mime types for the validation rules and a limit of two megabytes', function () {
    expect(ImageType::mimeTypes())->toBe(['image/png', 'image/jpeg', 'image/webp'])
        ->and(ImageType::MAX_KILOBYTES)->toBe(2048);
});

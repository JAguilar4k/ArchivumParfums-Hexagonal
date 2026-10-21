<?php

declare(strict_types=1);

use Src\Businesses\Logo\Application\Contracts\BusinessLogos;
use Src\Businesses\Logo\Application\DTOs\UploadBusinessLogoCommand;
use Src\Businesses\Logo\Application\UseCases\UploadBusinessLogo;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\Shared\InMemoryFileStorage;
use Tests\Support\Shared\RecordingTransactionRunner;

const LOGO_BUSINESS = '0192f0c4-ffff-7000-9000-000000000001';

/** Logos kept in memory by business. */
function inMemoryLogos(): BusinessLogos
{
    return new class implements BusinessLogos
    {
        /** @var array<string, string> */
        private array $paths = [];

        public function pathOf(BusinessId $business): ?string
        {
            return $this->paths[$business->value] ?? null;
        }

        public function save(BusinessId $business, string $path): void
        {
            $this->paths[$business->value] = $path;
        }
    };
}

/**
 * Wire the use case to in-memory ports for the first business.
 *
 * @return array{UploadBusinessLogo, InMemoryFileStorage, BusinessLogos}
 */
function logoWiring(?BusinessLogos $logos = null): array
{
    $logos ??= inMemoryLogos();
    $storage = new InMemoryFileStorage;

    return [
        new UploadBusinessLogo($logos, $storage, new FixedBusinessContext(new BusinessId(LOGO_BUSINESS)), new RecordingTransactionRunner),
        $storage,
        $logos,
    ];
}

it('keeps the file under a generated name with the extension of its real type and answers its address', function () {
    [$upload, $storage, $logos] = logoWiring();

    $url = $upload->execute(new UploadBusinessLogoCommand('/tmp/mi-logo-original.bmp', 'image/jpeg'));

    expect($url)->toBe('https://archivos.test/logos/archivo-1.jpg')
        ->and($logos->pathOf(new BusinessId(LOGO_BUSINESS)))->toBe('logos/archivo-1.jpg')
        ->and(array_keys($storage->files))->toBe(['logos/archivo-1.jpg']);
});

it('deletes the previous logo only after the transaction commits', function () {
    [$upload, $storage] = logoWiring();
    $upload->execute(new UploadBusinessLogoCommand('/tmp/uno.png', 'image/png'));

    $upload->execute(new UploadBusinessLogoCommand('/tmp/dos.png', 'image/png'));

    expect(array_keys($storage->files))->toBe(['logos/archivo-1.png', 'logos/archivo-2.png'])
        ->and($storage->scheduled)->toBe(['logos/archivo-1.png']);

    $storage->commit();

    expect(array_keys($storage->files))->toBe(['logos/archivo-2.png']);
});

it('refuses a type that is not accepted and stores nothing', function (string $mime) {
    [$upload, $storage] = logoWiring();

    expect(fn () => $upload->execute(new UploadBusinessLogoCommand('/tmp/x', $mime)))
        ->toThrow(InvalidValueException::class, 'files.image_type_invalid')
        ->and($storage->files)->toBe([]);
})->with(['image/gif', 'application/pdf']);

it('discards the new file and keeps the previous one when the logo cannot be saved', function () {
    $failing = new class implements BusinessLogos
    {
        public function pathOf(BusinessId $business): string
        {
            return 'logos/vigente.png';
        }

        public function save(BusinessId $business, string $path): void
        {
            throw new RuntimeException('The database is not answering.');
        }
    };
    [$upload, $storage] = logoWiring($failing);

    expect(fn () => $upload->execute(new UploadBusinessLogoCommand('/tmp/nuevo.png', 'image/png')))->toThrow(RuntimeException::class)
        ->and($storage->files)->toBe([])
        ->and($storage->scheduled)->toBe([]);
});

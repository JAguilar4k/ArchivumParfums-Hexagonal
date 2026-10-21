<?php

declare(strict_types=1);

use Src\Catalog\Perfumes\Application\Contracts\PerfumePhotos;
use Src\Catalog\Perfumes\Application\DTOs\UploadPerfumePhotoCommand;
use Src\Catalog\Perfumes\Application\UseCases\UploadPerfumePhoto;
use Src\Catalog\Perfumes\Domain\Entities\Perfume;
use Src\Catalog\Perfumes\Domain\Exceptions\PerfumeNotFoundException;
use Src\Catalog\Perfumes\Domain\ValueObjects\Brand;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeDetails;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeId;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeName;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\InMemoryPerfumeRepository;
use Tests\Support\Shared\InMemoryFileStorage;
use Tests\Support\Shared\RecordingTransactionRunner;

/**
 * Wire the use case to in-memory ports and a perfume that already exists.
 *
 * @return array{UploadPerfumePhoto, InMemoryFileStorage, Perfume, PerfumePhotos}
 */
function photoWiring(): array
{
    $perfumes = new InMemoryPerfumeRepository;
    $perfume = Perfume::create($perfumes->nextId(), new BusinessId('0192f0c4-ffff-7000-9000-000000000002'), new Brand('Casa Aurora'), new PerfumeName('Alba de Azahar'), PerfumeDetails::empty());
    $perfumes->save($perfume);
    $photos = new class implements PerfumePhotos
    {
        /** @var array<string, string> */
        private array $paths = [];

        public function pathOf(PerfumeId $perfume): ?string
        {
            return $this->paths[$perfume->value] ?? null;
        }

        public function save(PerfumeId $perfume, string $path): void
        {
            $this->paths[$perfume->value] = $path;
        }
    };
    $storage = new InMemoryFileStorage;

    return [new UploadPerfumePhoto($perfumes, $photos, $storage, new RecordingTransactionRunner), $storage, $perfume, $photos];
}

it('keeps the file as the photo of the perfume under a generated name and answers its address', function () {
    [$upload, $storage, $perfume, $photos] = photoWiring();

    $url = $upload->execute(new UploadPerfumePhotoCommand($perfume->id->value, '/tmp/foto-original.jpeg', 'image/webp'));

    expect($url)->toBe('https://archivos.test/perfumes/archivo-1.webp')
        ->and($photos->pathOf($perfume->id))->toBe('perfumes/archivo-1.webp')
        ->and(array_keys($storage->files))->toBe(['perfumes/archivo-1.webp']);
});

it('deletes the previous photo only after the transaction commits', function () {
    [$upload, $storage, $perfume] = photoWiring();
    $upload->execute(new UploadPerfumePhotoCommand($perfume->id->value, '/tmp/uno.png', 'image/png'));
    $upload->execute(new UploadPerfumePhotoCommand($perfume->id->value, '/tmp/dos.png', 'image/png'));

    expect($storage->scheduled)->toBe(['perfumes/archivo-1.png']);

    $storage->commit();

    expect(array_keys($storage->files))->toBe(['perfumes/archivo-2.png']);
});

it('treats a perfume that is not there, or an identity that is not one, as not found and stores nothing', function (string $id) {
    [$upload, $storage] = photoWiring();

    expect(fn () => $upload->execute(new UploadPerfumePhotoCommand($id, '/tmp/x.png', 'image/png')))
        ->toThrow(PerfumeNotFoundException::class)
        ->and($storage->files)->toBe([]);
})->with([
    'unknown' => '0192f0c4-0000-7000-9000-999999999999',
    'not an identity' => 'no-es-un-uuid',
]);

it('refuses a type that is not accepted before looking for the perfume', function () {
    [$upload, $storage, $perfume] = photoWiring();

    expect(fn () => $upload->execute(new UploadPerfumePhotoCommand($perfume->id->value, '/tmp/x', 'application/pdf')))
        ->toThrow(InvalidValueException::class, 'files.image_type_invalid')
        ->and($storage->files)->toBe([]);
});

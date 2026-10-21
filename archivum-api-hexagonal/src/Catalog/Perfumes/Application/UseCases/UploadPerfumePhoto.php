<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Application\UseCases;

use Src\Catalog\Perfumes\Application\Contracts\PerfumePhotos;
use Src\Catalog\Perfumes\Application\DTOs\UploadPerfumePhotoCommand;
use Src\Catalog\Perfumes\Domain\Contracts\PerfumeRepository;
use Src\Catalog\Perfumes\Domain\Exceptions\PerfumeNotFoundException;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeId;
use Src\Shared\Domain\Contracts\FileStorage;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\ImageType;
use Throwable;

final readonly class UploadPerfumePhoto
{
    /** Receive the repository, which only reaches the business of the session, the photos, the file storage and the transaction ports. */
    public function __construct(
        private PerfumeRepository $perfumes,
        private PerfumePhotos $photos,
        private FileStorage $storage,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Keep the uploaded file as the photo of a perfume of the business of the session and answer its address; one of another
     * business is treated as missing. The previous photo is deleted only after the transaction commits.
     *
     * @throws PerfumeNotFoundException
     */
    public function execute(UploadPerfumePhotoCommand $command): string
    {
        $type = ImageType::fromMime($command->mimeType);

        return $this->transaction->run(function () use ($command, $type): string {
            $id = $this->idOf($command->perfumeId);

            if ($this->perfumes->findForUpdate($id) === null) {
                throw PerfumeNotFoundException::create();
            }

            $previous = $this->photos->pathOf($id);
            $path = $this->storage->store('perfumes', $type->extension(), $command->sourcePath);

            try {
                $this->photos->save($id, $path);
            } catch (Throwable $exception) {
                $this->storage->discard($path);

                throw $exception;
            }

            $this->storage->deleteAfterCommit($previous);

            return $this->storage->url($path);
        });
    }

    /** An identity that is not a valid one cannot belong to any perfume. */
    private function idOf(string $id): PerfumeId
    {
        try {
            return new PerfumeId($id);
        } catch (InvalidValueException) {
            throw PerfumeNotFoundException::create();
        }
    }
}

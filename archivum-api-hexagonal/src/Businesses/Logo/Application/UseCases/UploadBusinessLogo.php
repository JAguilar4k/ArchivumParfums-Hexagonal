<?php

declare(strict_types=1);

namespace Src\Businesses\Logo\Application\UseCases;

use Src\Businesses\Logo\Application\Contracts\BusinessLogos;
use Src\Businesses\Logo\Application\DTOs\UploadBusinessLogoCommand;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Contracts\FileStorage;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\ImageType;
use Throwable;

final readonly class UploadBusinessLogo
{
    /** Receive the logos, the file storage, the business of the session and the transaction ports. */
    public function __construct(
        private BusinessLogos $logos,
        private FileStorage $storage,
        private BusinessContext $businessContext,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Keep the uploaded file as the logo of the business of the session and answer its address. The previous logo is deleted
     * only after the transaction commits, so a failure never leaves the business without the logo it had.
     */
    public function execute(UploadBusinessLogoCommand $command): string
    {
        $type = ImageType::fromMime($command->mimeType);
        $business = $this->businessContext->current();

        return $this->transaction->run(function () use ($command, $type, $business): string {
            $previous = $this->logos->pathOf($business);
            $path = $this->storage->store('logos', $type->extension(), $command->sourcePath);

            try {
                $this->logos->save($business, $path);
            } catch (Throwable $exception) {
                $this->storage->discard($path);

                throw $exception;
            }

            $this->storage->deleteAfterCommit($previous);

            return $this->storage->url($path);
        });
    }
}

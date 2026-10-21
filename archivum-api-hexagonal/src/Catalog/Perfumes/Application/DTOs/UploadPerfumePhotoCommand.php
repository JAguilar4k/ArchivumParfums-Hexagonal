<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Application\DTOs;

final readonly class UploadPerfumePhotoCommand
{
    /** Carry the perfume, the temporary file that was uploaded and the mime type detected in its content, not the one the client announced. */
    public function __construct(
        public string $perfumeId,
        public string $sourcePath,
        public string $mimeType,
    ) {}
}

<?php

declare(strict_types=1);

namespace Src\Businesses\Logo\Application\DTOs;

final readonly class UploadBusinessLogoCommand
{
    /** Carry the temporary file that was uploaded and the mime type detected in its content, not the one the client announced. */
    public function __construct(
        public string $sourcePath,
        public string $mimeType,
    ) {}
}

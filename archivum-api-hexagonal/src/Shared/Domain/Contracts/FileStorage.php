<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Contracts;

interface FileStorage
{
    /**
     * Keep a copy of a file under a name the storage generates, never the one the client sent, and answer the path it was kept at.
     */
    public function store(string $directory, string $extension, string $sourcePath): string;

    /** Delete a file right away, such as the new one when the operation that stored it failed. */
    public function discard(string $path): void;

    /** Delete a file once the open transaction commits, so a failure never leaves the record without its file; null deletes nothing. */
    public function deleteAfterCommit(?string $path): void;

    /** The public address of a stored file. */
    public function url(string $path): string;
}

<?php

declare(strict_types=1);

namespace Tests\Support\Shared;

use Src\Shared\Domain\Contracts\FileStorage;

final class InMemoryFileStorage implements FileStorage
{
    /** @var array<string, string> Source path of each file kept, by the path it was kept at. */
    public array $files = [];

    /** @var list<string> Paths that will be deleted when the transaction commits. */
    public array $scheduled = [];

    private int $sequence = 0;

    /** Keep the file under a predictable generated name. */
    public function store(string $directory, string $extension, string $sourcePath): string
    {
        $path = sprintf('%s/archivo-%d.%s', $directory, ++$this->sequence, $extension);
        $this->files[$path] = $sourcePath;

        return $path;
    }

    public function discard(string $path): void
    {
        unset($this->files[$path]);
    }

    public function deleteAfterCommit(?string $path): void
    {
        if ($path !== null) {
            $this->scheduled[] = $path;
        }
    }

    public function url(string $path): string
    {
        return "https://archivos.test/{$path}";
    }

    /** Commit the transaction: delete the files that were waiting for it. */
    public function commit(): void
    {
        foreach ($this->scheduled as $path) {
            unset($this->files[$path]);
        }

        $this->scheduled = [];
    }
}

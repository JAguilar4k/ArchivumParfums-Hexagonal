<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Src\Shared\Domain\Contracts\FileStorage;

/** Keep the files in the disk that `files.disk` names, so changing the disk is a matter of configuration. */
final readonly class LaravelFileStorage implements FileStorage
{
    public function store(string $directory, string $extension, string $sourcePath): string
    {
        $stored = $this->disk()->putFileAs($directory, $sourcePath, Str::uuid7().'.'.$extension);

        if (! is_string($stored)) {
            throw new RuntimeException('The file could not be stored.');
        }

        return $stored;
    }

    public function discard(string $path): void
    {
        $this->disk()->delete($path);
    }

    public function deleteAfterCommit(?string $path): void
    {
        if ($path === null) {
            return;
        }

        DB::afterCommit(fn () => $this->disk()->delete($path));
    }

    public function url(string $path): string
    {
        /** @var FilesystemAdapter $adapter */
        $adapter = $this->disk();

        return $adapter->url($path);
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('files.disk'));
    }
}

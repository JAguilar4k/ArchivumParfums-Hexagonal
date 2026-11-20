<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Storage;

use Aws\CommandInterface;
use Aws\Middleware;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Src\Shared\Domain\Contracts\FileStorage;

/** Keep the storage location of each photo stable when R2 is enabled or disabled. */
final readonly class LaravelFileStorage implements FileStorage
{
    private const R2_PREFIX = 'r2:';

    private const MAX_PHOTO_PATH_LENGTH = 255;

    private const REMOVE_R2_ACL = 'archivum.r2.remove_acl';

    public function store(string $directory, string $extension, string $sourcePath): string
    {
        $filename = Str::uuid7().'.'.$extension;
        $usesR2 = in_array($directory, Config::array('files.r2_directories'), true)
            && $this->r2Configured()
            && strlen(self::R2_PREFIX.$this->r2PublicOrigin()."/{$directory}/{$filename}") <= self::MAX_PHOTO_PATH_LENGTH;
        $disk = $usesR2 ? $this->r2UploadDisk() : $this->disk((string) config('files.disk'));
        $stored = $disk->putFileAs($directory, $sourcePath, $filename);

        if (! is_string($stored)) {
            throw new RuntimeException('The file could not be stored.');
        }

        return $usesR2 ? self::R2_PREFIX.$disk->url($stored) : $stored;
    }

    public function discard(string $path): void
    {
        if (! str_starts_with($path, self::R2_PREFIX)) {
            $this->disk((string) config('files.disk'))->delete($path);

            return;
        }

        $url = $this->url($path);
        $key = parse_url($url, PHP_URL_PATH);

        if (! $this->r2Configured() || ! str_starts_with($url, $this->r2PublicOrigin().'/') || ! is_string($key)) {
            Log::warning('An R2 perfume photo could not be deleted with the current configuration.');

            return;
        }

        $this->disk('r2')->delete(ltrim($key, '/'));
    }

    public function deleteAfterCommit(?string $path): void
    {
        if ($path !== null) {
            DB::afterCommit(fn () => $this->discard($path));
        }
    }

    public function url(string $path): string
    {
        if (str_starts_with($path, self::R2_PREFIX)) {
            return substr($path, strlen(self::R2_PREFIX));
        }

        return $this->disk((string) config('files.disk'))->url($path);
    }

    public function imageVariants(string $path): array
    {
        if (! str_starts_with($path, self::R2_PREFIX)) {
            return [];
        }

        $url = $this->url($path);
        $key = parse_url($url, PHP_URL_PATH);
        if (! is_string($key) || $key === '') {
            return [];
        }

        $origin = substr($url, 0, -strlen($key));
        /** @var list<int> $widths */
        $widths = Config::array('files.r2_image_widths');
        sort($widths);

        return array_map(static fn (int $width): array => [
            'width' => $width,
            'url' => "{$origin}/cdn-cgi/image/width={$width},fit=contain,format=auto{$key}",
        ], $widths);
    }

    private function r2Configured(): bool
    {
        return filled(config('filesystems.disks.r2.key'))
            && filled(config('filesystems.disks.r2.secret'))
            && filled(config('filesystems.disks.r2.bucket'))
            && filled(config('filesystems.disks.r2.endpoint'))
            && preg_match('#\Ahttps://[a-z0-9.-]+\z#i', $this->r2PublicOrigin()) === 1;
    }

    private function r2PublicOrigin(): string
    {
        return rtrim((string) config('filesystems.disks.r2.url'), '/');
    }

    /** R2 serves public objects through the bucket domain and does not support the ACL sent by Flysystem. */
    private function r2UploadDisk(): FilesystemAdapter
    {
        $disk = $this->disk('r2');

        if ($disk instanceof AwsS3V3Adapter) {
            $handlers = $disk->getClient()->getHandlerList();
            $handlers->remove(self::REMOVE_R2_ACL);
            $handlers->appendInit(Middleware::mapCommand(static function (CommandInterface $command): CommandInterface {
                unset($command['ACL']);

                return $command;
            }), self::REMOVE_R2_ACL);
        }

        return $disk;
    }

    private function disk(string $name): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($name);

        return $disk;
    }
}

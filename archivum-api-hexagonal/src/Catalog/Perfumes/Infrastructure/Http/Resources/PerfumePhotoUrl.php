<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PerfumePhotoUrl extends JsonResource
{
    /** Wrap the address of the photo. */
    public function __construct(private readonly string $url)
    {
        parent::__construct($url);
    }

    /**
     * Expose only the address of the photo.
     *
     * @return array{foto_url: string}
     */
    public function toArray(Request $request): array
    {
        return ['foto_url' => $this->url];
    }
}

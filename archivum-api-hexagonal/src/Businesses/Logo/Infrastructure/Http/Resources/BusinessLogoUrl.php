<?php

declare(strict_types=1);

namespace Src\Businesses\Logo\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BusinessLogoUrl extends JsonResource
{
    /** Wrap the address of the logo. */
    public function __construct(private readonly string $url)
    {
        parent::__construct($url);
    }

    /**
     * Expose only the address of the logo.
     *
     * @return array{logo_url: string}
     */
    public function toArray(Request $request): array
    {
        return ['logo_url' => $this->url];
    }
}

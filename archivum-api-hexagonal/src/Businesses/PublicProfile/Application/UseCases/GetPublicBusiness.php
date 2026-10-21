<?php

declare(strict_types=1);

namespace Src\Businesses\PublicProfile\Application\UseCases;

use Src\Businesses\PublicProfile\Application\Contracts\PublicBusinessReader;
use Src\Businesses\PublicProfile\Application\DTOs\PublicBusinessView;
use Src\Businesses\PublicProfile\Application\Exceptions\PublicBusinessNotFoundException;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Contracts\FileStorage;

final readonly class GetPublicBusiness
{
    /** Receive the reader, the file storage and the business that the slug of the address resolved to. */
    public function __construct(
        private PublicBusinessReader $reader,
        private FileStorage $storage,
        private BusinessContext $businessContext,
    ) {}

    /**
     * Show the public profile of the business of the address.
     *
     * @throws PublicBusinessNotFoundException
     */
    public function execute(): PublicBusinessView
    {
        $business = $this->reader->find($this->businessContext->current())
            ?? throw PublicBusinessNotFoundException::create();

        return new PublicBusinessView(
            $business->name,
            $business->slug,
            $business->logoPath === null ? null : $this->storage->url($business->logoPath),
            $business->appointmentPlace,
            $business->timezone,
        );
    }
}

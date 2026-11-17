<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Application\UseCases;

use Src\Scheduling\Services\Domain\Contracts\ServiceRepository;
use Src\Scheduling\Services\Domain\Entities\Service;
use Src\Scheduling\Services\Domain\Exceptions\ServiceNotFoundException;
use Src\Scheduling\Services\Domain\ValueObjects\ServiceId;

/** Read one service of the business of the session. */
final readonly class ShowService
{
    /** Receive the repository. */
    public function __construct(private ServiceRepository $services) {}

    /**
     * Find the service or answer that it does not exist.
     *
     * @throws ServiceNotFoundException
     */
    public function execute(string $serviceId): Service
    {
        return $this->services->findById(new ServiceId($serviceId))
            ?? throw ServiceNotFoundException::create();
    }
}

<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Application\UseCases;

use Src\Scheduling\Services\Domain\Contracts\ServiceRepository;
use Src\Scheduling\Services\Domain\Entities\Service;

/** List every service of the business of the session, active and inactive. */
final readonly class ListServices
{
    /** Receive the repository. */
    public function __construct(private ServiceRepository $services) {}

    /**
     * The services of the business of the session, ordered by name.
     *
     * @return list<Service>
     */
    public function execute(): array
    {
        return $this->services->all();
    }
}

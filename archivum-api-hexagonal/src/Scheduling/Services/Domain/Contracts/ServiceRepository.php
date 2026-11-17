<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Domain\Contracts;

use Src\Scheduling\Services\Domain\Entities\Service;
use Src\Scheduling\Services\Domain\ValueObjects\ServiceId;

/**
 * Port of the services of one business. Every query reaches only the rows of the business of the context, and the
 * row-level policy of PostgreSQL is the second barrier.
 */
interface ServiceRepository
{
    /** Generate a time-ordered identity for a new service; the adapter owns the generation, the domain does not. */
    public function nextId(): ServiceId;

    /** Write the service in the business of the context. */
    public function save(Service $service): void;

    /** Find a service of the business of the context, or null when it does not exist or belongs to another business. */
    public function findById(ServiceId $id): ?Service;

    /**
     * Every service of the business of the context, active and inactive, ordered by name.
     *
     * @return list<Service>
     */
    public function all(): array;
}

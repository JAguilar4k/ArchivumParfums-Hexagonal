<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Domain\Contracts;

use Src\Businesses\Profile\Domain\Entities\Business;
use Src\Businesses\Profile\Domain\Exceptions\SlugAlreadyInUseException;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSlug;
use Src\Shared\Domain\ValueObjects\BusinessId;

interface BusinessRepository
{
    /** Generate the identity for a new business. */
    public function nextId(): BusinessId;

    /**
     * Persist a new or modified business.
     *
     * @throws SlugAlreadyInUseException
     */
    public function save(Business $business): void;

    /** Find a business by its identity. */
    public function find(BusinessId $id): ?Business;

    /** Find a business by the slug of its public addresses. */
    public function findBySlug(BusinessSlug $slug): ?Business;
}

<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Domain\Contracts;

use Src\Businesses\Profile\Domain\Entities\Business;
use Src\Businesses\Profile\Domain\Exceptions\SlugAlreadyInUseException;
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
}

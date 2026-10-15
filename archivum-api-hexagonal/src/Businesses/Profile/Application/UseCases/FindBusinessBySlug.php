<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Application\UseCases;

use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Businesses\Profile\Domain\Entities\Business;
use Src\Businesses\Profile\Domain\Exceptions\BusinessNotFoundException;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSlug;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class FindBusinessBySlug
{
    /** Receive the business repository port. */
    public function __construct(private BusinessRepository $businesses) {}

    /**
     * Find the business of a public address; a malformed slug is just another slug that does not exist.
     *
     * @throws BusinessNotFoundException
     */
    public function execute(string $slug): Business
    {
        try {
            $value = new BusinessSlug($slug);
        } catch (InvalidValueException) {
            throw BusinessNotFoundException::create();
        }

        return $this->businesses->findBySlug($value) ?? throw BusinessNotFoundException::create();
    }
}

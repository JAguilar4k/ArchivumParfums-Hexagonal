<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use Src\Businesses\Profile\Domain\Contracts\BusinessRepository;
use Src\Businesses\Profile\Domain\Entities\Business;
use Src\Businesses\Profile\Domain\Exceptions\SlugAlreadyInUseException;
use Src\Shared\Domain\ValueObjects\BusinessId;

final class InMemoryBusinessRepository implements BusinessRepository
{
    /** @var array<string, Business> */
    private array $businesses = [];

    private int $sequence = 0;

    /** Generate a predictable identity. */
    public function nextId(): BusinessId
    {
        return new BusinessId(sprintf('0192f0c4-0000-7000-9000-%012d', ++$this->sequence));
    }

    /** Store the business enforcing a unique slug. */
    public function save(Business $business): void
    {
        foreach ($this->businesses as $stored) {
            if ($stored->slug->value === $business->slug->value && ! $stored->id->equals($business->id)) {
                throw SlugAlreadyInUseException::create();
            }
        }

        $this->businesses[$business->id->value] = $business;
    }

    /** Find a business by its identity. */
    public function find(BusinessId $id): ?Business
    {
        return $this->businesses[$id->value] ?? null;
    }

    /**
     * List the stored businesses.
     *
     * @return list<Business>
     */
    public function all(): array
    {
        return array_values($this->businesses);
    }
}

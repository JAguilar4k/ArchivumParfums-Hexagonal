<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Application\UseCases;

use Src\Catalog\Perfumes\Domain\Contracts\PerfumeRepository;
use Src\Catalog\Perfumes\Domain\Entities\PerfumeVariant;
use Src\Catalog\Perfumes\Domain\Exceptions\PerfumeNotFoundException;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeId;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class ListPerfumeVariants
{
    /** Receive the repository, which only reaches the business of the session. */
    public function __construct(private PerfumeRepository $perfumes) {}

    /**
     * List every presentation of a perfume of the business of the session, active or not, by milliliters.
     *
     * @return list<PerfumeVariant>
     *
     * @throws PerfumeNotFoundException
     */
    public function execute(string $perfumeId): array
    {
        try {
            $id = new PerfumeId($perfumeId);
        } catch (InvalidValueException) {
            throw PerfumeNotFoundException::create();
        }

        return ($this->perfumes->find($id) ?? throw PerfumeNotFoundException::create())->variants;
    }
}

<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Application\UseCases;

use Src\Catalog\Perfumes\Domain\Contracts\PerfumeRepository;
use Src\Catalog\Perfumes\Domain\Entities\Perfume;
use Src\Catalog\Perfumes\Domain\Exceptions\PerfumeNotFoundException;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeId;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class ShowPerfume
{
    /** Receive the repository, which only reaches the business of the session. */
    public function __construct(private PerfumeRepository $perfumes) {}

    /**
     * Show a perfume of the business of the session with its presentations; one of another business is treated as missing.
     *
     * @throws PerfumeNotFoundException
     */
    public function execute(string $perfumeId): Perfume
    {
        try {
            $id = new PerfumeId($perfumeId);
        } catch (InvalidValueException) {
            throw PerfumeNotFoundException::create();
        }

        return $this->perfumes->find($id) ?? throw PerfumeNotFoundException::create();
    }
}

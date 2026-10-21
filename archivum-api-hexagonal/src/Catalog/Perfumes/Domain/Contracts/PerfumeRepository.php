<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Domain\Contracts;

use Src\Catalog\Perfumes\Domain\Entities\Perfume;
use Src\Catalog\Perfumes\Domain\Exceptions\DuplicatePerfumeException;
use Src\Catalog\Perfumes\Domain\Exceptions\DuplicateVariantSizeException;
use Src\Catalog\Perfumes\Domain\Exceptions\PerfumeNotFoundException;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeId;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeVariantId;

interface PerfumeRepository
{
    /** Generate the identity of a new perfume. */
    public function nextId(): PerfumeId;

    /** Generate the identity of a new presentation. */
    public function nextVariantId(): PerfumeVariantId;

    /**
     * Persist a new or modified perfume with its notes, tags and presentations in the current business; a perfume of another
     * business is refused, as one that does not exist.
     *
     * @throws PerfumeNotFoundException
     * @throws DuplicatePerfumeException
     * @throws DuplicateVariantSizeException
     */
    public function save(Perfume $perfume): void;

    /** Find a perfume of the current business by its identity. */
    public function find(PerfumeId $id): ?Perfume;

    /** Find a perfume of the current business and lock its stored state until the transaction ends. */
    public function findForUpdate(PerfumeId $id): ?Perfume;
}

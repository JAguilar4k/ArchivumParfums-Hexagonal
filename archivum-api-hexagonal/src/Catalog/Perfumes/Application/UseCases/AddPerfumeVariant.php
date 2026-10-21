<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Application\UseCases;

use Src\Catalog\Perfumes\Application\DTOs\AddPerfumeVariantCommand;
use Src\Catalog\Perfumes\Domain\Contracts\PerfumeRepository;
use Src\Catalog\Perfumes\Domain\Entities\PerfumeVariant;
use Src\Catalog\Perfumes\Domain\Exceptions\DuplicateVariantSizeException;
use Src\Catalog\Perfumes\Domain\Exceptions\PerfumeNotFoundException;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeId;
use Src\Catalog\Perfumes\Domain\ValueObjects\SizeMl;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\Colones;

final readonly class AddPerfumeVariant
{
    /** Receive the repository, which only reaches the business of the session, and the transaction ports. */
    public function __construct(
        private PerfumeRepository $perfumes,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Add an active presentation to a perfume of the business of the session; the perfume refuses repeated milliliters.
     *
     * @throws PerfumeNotFoundException
     * @throws DuplicateVariantSizeException
     */
    public function execute(AddPerfumeVariantCommand $command): PerfumeVariant
    {
        return $this->transaction->run(function () use ($command): PerfumeVariant {
            $perfume = $this->perfumes->findForUpdate($this->idOf($command->perfumeId))
                ?? throw PerfumeNotFoundException::create();

            $id = $this->perfumes->nextVariantId();
            $perfume->addVariant(
                $id,
                new SizeMl($command->ml),
                null,
                $command->manualPrice === null ? null : new Colones($command->manualPrice),
            );

            $this->perfumes->save($perfume);

            return $perfume->variant($id);
        });
    }

    /** An identity that is not a valid one cannot belong to any perfume. */
    private function idOf(string $id): PerfumeId
    {
        try {
            return new PerfumeId($id);
        } catch (InvalidValueException) {
            throw PerfumeNotFoundException::create();
        }
    }
}

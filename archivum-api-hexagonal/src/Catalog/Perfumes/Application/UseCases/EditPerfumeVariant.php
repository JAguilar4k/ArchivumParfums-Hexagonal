<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Application\UseCases;

use Src\Catalog\Perfumes\Application\DTOs\EditPerfumeVariantCommand;
use Src\Catalog\Perfumes\Domain\Contracts\PerfumeRepository;
use Src\Catalog\Perfumes\Domain\Entities\PerfumeVariant;
use Src\Catalog\Perfumes\Domain\Exceptions\DuplicateVariantSizeException;
use Src\Catalog\Perfumes\Domain\Exceptions\PerfumeNotFoundException;
use Src\Catalog\Perfumes\Domain\Exceptions\PerfumeVariantNotFoundException;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeId;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeVariantId;
use Src\Catalog\Perfumes\Domain\ValueObjects\SizeMl;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\Colones;

final readonly class EditPerfumeVariant
{
    /** Receive the repository, which only reaches the business of the session, and the transaction ports. */
    public function __construct(
        private PerfumeRepository $perfumes,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Change the milliliters, the manual price or the state of a presentation of a perfume of the business of the session.
     *
     * @throws PerfumeNotFoundException
     * @throws PerfumeVariantNotFoundException
     * @throws DuplicateVariantSizeException
     */
    public function execute(EditPerfumeVariantCommand $command): PerfumeVariant
    {
        return $this->transaction->run(function () use ($command): PerfumeVariant {
            $perfume = $this->perfumes->findForUpdate($this->perfumeIdOf($command->perfumeId))
                ?? throw PerfumeNotFoundException::create();

            $id = $this->variantIdOf($command->variantId);
            $variant = $perfume->variant($id);
            $changes = $command->changes;

            $perfume->editVariant(
                $id,
                isset($changes['ml']) ? new SizeMl($changes['ml']) : $variant->size,
                array_key_exists('manual_price', $changes)
                    ? ($changes['manual_price'] === null ? null : new Colones($changes['manual_price']))
                    : $variant->manualPrice,
                $changes['active'] ?? $variant->isActive,
            );

            $this->perfumes->save($perfume);

            return $perfume->variant($id);
        });
    }

    /** An identity that is not a valid one cannot belong to any perfume. */
    private function perfumeIdOf(string $id): PerfumeId
    {
        try {
            return new PerfumeId($id);
        } catch (InvalidValueException) {
            throw PerfumeNotFoundException::create();
        }
    }

    /** An identity that is not a valid one cannot belong to any presentation. */
    private function variantIdOf(string $id): PerfumeVariantId
    {
        try {
            return new PerfumeVariantId($id);
        } catch (InvalidValueException) {
            throw PerfumeVariantNotFoundException::create();
        }
    }
}

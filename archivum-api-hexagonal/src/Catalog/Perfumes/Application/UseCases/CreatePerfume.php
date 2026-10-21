<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Application\UseCases;

use Src\Catalog\Perfumes\Application\DTOs\CreatePerfumeCommand;
use Src\Catalog\Perfumes\Domain\Contracts\PerfumeRepository;
use Src\Catalog\Perfumes\Domain\Entities\Perfume;
use Src\Catalog\Perfumes\Domain\ValueObjects\Brand;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeDetails;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeName;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeNote;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeTag;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class CreatePerfume
{
    /** Receive the repository, the business of the session and the transaction ports. */
    public function __construct(
        private PerfumeRepository $perfumes,
        private BusinessContext $businessContext,
        private TransactionRunner $transaction,
    ) {}

    /** Register a perfume with its notes and tags in the business of the session. */
    public function execute(CreatePerfumeCommand $command): Perfume
    {
        return $this->transaction->run(function () use ($command): Perfume {
            $perfume = Perfume::create(
                $this->perfumes->nextId(),
                $this->businessContext->current(),
                new Brand($command->brand),
                new PerfumeName($command->name),
                new PerfumeDetails($command->concentration, $command->gender, $command->family, $command->description),
                $this->notes($command),
                $this->tags($command),
            );

            if (! $command->isActive) {
                $perfume->deactivate();
            }

            $this->perfumes->save($perfume);

            return $perfume;
        });
    }

    /** @return list<PerfumeNote> */
    private function notes(CreatePerfumeCommand $command): array
    {
        $notes = [];

        foreach ($command->notes as $layer => $texts) {
            foreach ($texts as $text) {
                $notes[] = PerfumeNote::fromStored($layer, $text);
            }
        }

        return $notes;
    }

    /** @return list<PerfumeTag> */
    private function tags(CreatePerfumeCommand $command): array
    {
        $tags = [];

        foreach ($command->tags as $kind => $values) {
            foreach ($values as $value) {
                $tags[] = PerfumeTag::fromStored($kind, $value);
            }
        }

        return $tags;
    }
}

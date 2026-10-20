<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Application\UseCases;

use Src\Catalog\Perfumes\Application\DTOs\EditPerfumeCommand;
use Src\Catalog\Perfumes\Domain\Contracts\PerfumeRepository;
use Src\Catalog\Perfumes\Domain\Entities\Perfume;
use Src\Catalog\Perfumes\Domain\Exceptions\DuplicatePerfumeException;
use Src\Catalog\Perfumes\Domain\Exceptions\PerfumeNotFoundException;
use Src\Catalog\Perfumes\Domain\ValueObjects\Brand;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeDetails;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeId;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeName;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeNote;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeTag;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class EditPerfume
{
    /** Receive the repository, which only reaches the business of the session, and the transaction ports. */
    public function __construct(
        private PerfumeRepository $perfumes,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Apply the requested changes to a perfume of the business of the session; one of another business is treated as missing.
     *
     * @throws PerfumeNotFoundException
     * @throws DuplicatePerfumeException
     */
    public function execute(EditPerfumeCommand $command): Perfume
    {
        return $this->transaction->run(function () use ($command): Perfume {
            $perfume = $this->perfumes->findForUpdate($this->idOf($command->perfumeId))
                ?? throw PerfumeNotFoundException::create();

            $changes = $command->changes;

            $perfume->revise(
                isset($changes['brand']) ? new Brand($changes['brand']) : $perfume->brand,
                isset($changes['name']) ? new PerfumeName($changes['name']) : $perfume->name,
                new PerfumeDetails(
                    array_key_exists('concentration', $changes) ? $changes['concentration'] : $perfume->details->concentration,
                    array_key_exists('gender', $changes) ? $changes['gender'] : $perfume->details->gender,
                    array_key_exists('family', $changes) ? $changes['family'] : $perfume->details->family,
                    array_key_exists('description', $changes) ? $changes['description'] : $perfume->details->description,
                ),
                $this->notes($perfume, $changes['notes'] ?? []),
                $this->tags($perfume, $changes['tags'] ?? []),
            );

            if (isset($changes['active'])) {
                $changes['active'] ? $perfume->activate() : $perfume->deactivate();
            }

            $this->perfumes->save($perfume);

            return $perfume;
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

    /**
     * Keep the notes of the layers that were not sent and replace the ones of the layers that were.
     *
     * @param  array<string, list<string>>  $replaced
     * @return list<PerfumeNote>
     */
    private function notes(Perfume $perfume, array $replaced): array
    {
        $notes = array_values(array_filter(
            $perfume->notes,
            fn (PerfumeNote $note): bool => ! array_key_exists($note->layer->value, $replaced),
        ));

        foreach ($replaced as $layer => $texts) {
            foreach ($texts as $text) {
                $notes[] = PerfumeNote::fromStored($layer, $text);
            }
        }

        return $notes;
    }

    /**
     * Keep the tags of the kinds that were not sent and replace the ones of the kinds that were.
     *
     * @param  array<string, list<string>>  $replaced
     * @return list<PerfumeTag>
     */
    private function tags(Perfume $perfume, array $replaced): array
    {
        $tags = array_values(array_filter(
            $perfume->tags,
            fn (PerfumeTag $tag): bool => ! array_key_exists($tag->kind->value, $replaced),
        ));

        foreach ($replaced as $kind => $values) {
            foreach ($values as $value) {
                $tags[] = PerfumeTag::fromStored($kind, $value);
            }
        }

        return $tags;
    }
}

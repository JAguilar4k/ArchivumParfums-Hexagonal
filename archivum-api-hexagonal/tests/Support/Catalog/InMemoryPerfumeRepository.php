<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use Src\Catalog\Perfumes\Application\Contracts\PerfumeSearch;
use Src\Catalog\Perfumes\Application\DTOs\PerfumePage;
use Src\Catalog\Perfumes\Domain\Contracts\PerfumeRepository;
use Src\Catalog\Perfumes\Domain\Entities\Perfume;
use Src\Catalog\Perfumes\Domain\Exceptions\DuplicatePerfumeException;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeId;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeVariantId;

final class InMemoryPerfumeRepository implements PerfumeRepository, PerfumeSearch
{
    /** @var array<string, Perfume> */
    private array $perfumes = [];

    private int $sequence = 0;

    /** Generate a predictable identity. */
    public function nextId(): PerfumeId
    {
        return new PerfumeId(sprintf('0192f0c4-0000-7000-9000-%012d', ++$this->sequence));
    }

    /** Generate a predictable identity for a presentation. */
    public function nextVariantId(): PerfumeVariantId
    {
        return new PerfumeVariantId(sprintf('0192f0c4-0001-7000-9000-%012d', ++$this->sequence));
    }

    /** Store the perfume refusing a second one with the same brand, name and concentration in the business. */
    public function save(Perfume $perfume): void
    {
        foreach ($this->perfumes as $stored) {
            if (! $stored->id->equals($perfume->id) && $this->key($stored) === $this->key($perfume)) {
                throw DuplicatePerfumeException::create();
            }
        }

        $this->perfumes[$perfume->id->value] = $perfume;
    }

    /** Find a perfume by its identity. */
    public function find(PerfumeId $id): ?Perfume
    {
        return $this->perfumes[$id->value] ?? null;
    }

    /** Return the current state; an in-memory operation has no competing connection. */
    public function findForUpdate(PerfumeId $id): ?Perfume
    {
        return $this->find($id);
    }

    /** Search the stored perfumes by brand and name, ordered like the database does, and page the result. */
    public function search(?string $text, int $page, int $perPage): PerfumePage
    {
        $found = array_values(array_filter(
            $this->perfumes,
            fn (Perfume $perfume): bool => $text === null
                || str_contains(mb_strtolower($perfume->brand->value), mb_strtolower($text))
                || str_contains(mb_strtolower($perfume->name->value), mb_strtolower($text)),
        ));
        usort($found, fn (Perfume $a, Perfume $b): int => [mb_strtolower($a->brand->value), mb_strtolower($a->name->value)] <=> [mb_strtolower($b->brand->value), mb_strtolower($b->name->value)]);

        return new PerfumePage(array_slice($found, ($page - 1) * $perPage, $perPage), count($found), $page, $perPage);
    }

    /** Count the stored perfumes. */
    public function count(): int
    {
        return count($this->perfumes);
    }

    private function key(Perfume $perfume): string
    {
        return implode('|', [
            $perfume->businessId->value,
            mb_strtolower($perfume->brand->value),
            mb_strtolower($perfume->name->value),
            mb_strtolower($perfume->details->concentration ?? ''),
        ]);
    }
}

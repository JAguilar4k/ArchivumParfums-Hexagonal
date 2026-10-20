<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Application\DTOs;

final readonly class EditPerfumeCommand
{
    /**
     * Carry the fields to change; an absent key keeps its current value and, for the texts that may be missing, null clears them.
     * The notes of a layer or the tags of a kind that are sent replace the previous ones of that layer or kind.
     *
     * @param array{
     *     brand?: string,
     *     name?: string,
     *     concentration?: string|null,
     *     gender?: string|null,
     *     family?: string|null,
     *     description?: string|null,
     *     notes?: array{top?: list<string>, heart?: list<string>, base?: list<string>},
     *     tags?: array{time_of_day?: list<string>, climate?: list<string>, occasion?: list<string>},
     *     active?: bool,
     * } $changes
     */
    public function __construct(
        public string $perfumeId,
        public array $changes,
    ) {}
}

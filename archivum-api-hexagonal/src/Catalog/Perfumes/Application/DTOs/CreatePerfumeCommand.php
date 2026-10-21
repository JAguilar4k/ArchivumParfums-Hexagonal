<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Application\DTOs;

final readonly class CreatePerfumeCommand
{
    /**
     * Carry the data of a new perfume with the vocabulary of the domain; the business is not part of it, it comes from the session.
     *
     * @param  array{top: list<string>, heart: list<string>, base: list<string>}  $notes  The notes of each layer.
     * @param  array{time_of_day: list<string>, climate: list<string>, occasion: list<string>}  $tags  The tags of each kind, with the values stored in the database.
     */
    public function __construct(
        public string $brand,
        public string $name,
        public ?string $concentration,
        public ?string $gender,
        public ?string $family,
        public ?string $description,
        public array $notes,
        public array $tags,
        public bool $isActive,
    ) {}
}

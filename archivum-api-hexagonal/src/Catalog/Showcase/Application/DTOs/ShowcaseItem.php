<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Application\DTOs;

final readonly class ShowcaseItem
{
    /**
     * A perfume of the showcase. The reader fills the path of the photo and the use case turns it into an address.
     *
     * @param  array{time_of_day: list<string>, climate: list<string>, occasion: list<string>}  $tags  The tags by kind, with the values the database stores.
     * @param  list<ShowcaseVariant>  $variants  Only the presentations that can be sold.
     * @param  list<array{width: int, url: string}>  $photoVariants  Public image variants in ascending order of width.
     */
    public function __construct(
        public string $id,
        public string $brand,
        public string $name,
        public ?string $concentration,
        public ?string $family,
        public ?string $photoPath,
        public ?string $photoUrl,
        public array $tags,
        public array $variants,
        public array $photoVariants = [],
    ) {}

    /** @param  list<array{width: int, url: string}>  $photoVariants */
    public function withPhoto(string $photoUrl, array $photoVariants): self
    {
        return new self($this->id, $this->brand, $this->name, $this->concentration, $this->family, $this->photoPath, $photoUrl, $this->tags, $this->variants, $photoVariants);
    }
}

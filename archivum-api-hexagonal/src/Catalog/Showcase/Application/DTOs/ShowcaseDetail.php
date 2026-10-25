<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Application\DTOs;

final readonly class ShowcaseDetail
{
    /**
     * A perfume of the showcase with everything a visitor may know about it. The reader fills the path of the photo and the
     * use case turns it into an address.
     *
     * @param  array{top: list<string>, heart: list<string>, base: list<string>}  $notes  The notes by layer.
     * @param  array{time_of_day: list<string>, climate: list<string>, occasion: list<string>}  $tags  The tags by kind, with the values the database stores.
     * @param  list<ShowcaseVariant>  $variants  Only the presentations that can be sold.
     * @param  list<ShowcaseDecant>  $decants  Only the sizes currently available from an open bottle.
     */
    public function __construct(
        public string $id,
        public string $brand,
        public string $name,
        public ?string $concentration,
        public ?string $gender,
        public ?string $family,
        public ?string $description,
        public ?string $photoPath,
        public ?string $photoUrl,
        public array $notes,
        public array $tags,
        public array $variants,
        public array $decants = [],
    ) {}

    /** The same perfume with the address of its photo. */
    public function withPhotoUrl(?string $photoUrl): self
    {
        return new self(
            $this->id, $this->brand, $this->name, $this->concentration, $this->gender, $this->family, $this->description,
            $this->photoPath, $photoUrl, $this->notes, $this->tags, $this->variants, $this->decants,
        );
    }
}

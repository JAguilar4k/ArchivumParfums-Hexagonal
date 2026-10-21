<?php

declare(strict_types=1);

use Src\Catalog\Showcase\Application\Contracts\ShowcaseReader;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseFilters;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseItem;
use Src\Catalog\Showcase\Application\DTOs\ShowcasePage;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseVariant;
use Src\Catalog\Showcase\Application\UseCases\GetShowcase;
use Tests\Support\Shared\InMemoryFileStorage;

/** A reader that answers a page of two perfumes, one with a photo and one without, and remembers the filters it got. */
function showcaseReader(): ShowcaseReader
{
    return new class implements ShowcaseReader
    {
        public ?ShowcaseFilters $received = null;

        public function page(ShowcaseFilters $filters): ShowcasePage
        {
            $this->received = $filters;
            $tags = ['time_of_day' => ['night'], 'climate' => ['cold'], 'occasion' => ['Cena']];

            return new ShowcasePage([
                new ShowcaseItem('0192f0c4-0000-7000-9000-000000000001', 'Tom Ford', 'Oud Wood', 'EDP', 'Amaderada', 'perfumes/oud.png', null, $tags, [new ShowcaseVariant(50, 185000)]),
                new ShowcaseItem('0192f0c4-0000-7000-9000-000000000002', 'Casa Aurora', 'Alba de Azahar', null, null, null, null, $tags, [new ShowcaseVariant(50, 98000)]),
            ], 2, 1, 20);
        }
    };
}

it('gives each photo its address and leaves the perfumes without one as they are', function () {
    $page = (new GetShowcase(showcaseReader(), new InMemoryFileStorage))->execute(new ShowcaseFilters);

    expect($page->items[0]->photoUrl)->toBe('https://archivos.test/perfumes/oud.png')
        ->and($page->items[1]->photoUrl)->toBeNull()
        ->and($page->total)->toBe(2)
        ->and($page->perPage)->toBe(20);
});

it('passes the filters to the reader as they came', function () {
    $reader = showcaseReader();
    $filters = new ShowcaseFilters(text: 'oud', moment: 'night', maxPrice: 200000, page: 2, perPage: 5);

    (new GetShowcase($reader, new InMemoryFileStorage))->execute($filters);

    expect($reader->received)->toBe($filters);
});

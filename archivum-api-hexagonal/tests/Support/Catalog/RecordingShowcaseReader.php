<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use Src\Catalog\Showcase\Application\Contracts\ShowcaseReader;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseFilters;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseItem;
use Src\Catalog\Showcase\Application\DTOs\ShowcasePage;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseVariant;

/** A reader that answers a page of two perfumes, one with a photo and one without, and remembers the filters it got. */
final class RecordingShowcaseReader implements ShowcaseReader
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
}

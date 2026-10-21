<?php

declare(strict_types=1);

use Src\Catalog\Showcase\Application\Contracts\ShowcaseReader;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseDetail;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseFilters;
use Src\Catalog\Showcase\Application\DTOs\ShowcasePage;
use Src\Catalog\Showcase\Application\DTOs\ShowcaseVariant;
use Src\Catalog\Showcase\Application\Exceptions\ShowcasePerfumeNotFoundException;
use Src\Catalog\Showcase\Application\UseCases\GetShowcasePerfume;
use Tests\Support\Shared\InMemoryFileStorage;

const SHOWCASE_PERFUME = '0192f0c4-0000-7000-9000-000000000001';

/** A reader that knows one perfume, with a photo, and none other. */
function detailReader(): ShowcaseReader
{
    return new class implements ShowcaseReader
    {
        public function page(ShowcaseFilters $filters): ShowcasePage
        {
            return new ShowcasePage([], 0, 1, 20);
        }

        public function find(string $perfumeId): ?ShowcaseDetail
        {
            return $perfumeId === SHOWCASE_PERFUME
                ? new ShowcaseDetail(
                    SHOWCASE_PERFUME, 'Maison Noctis', 'Oud Nocturno', 'EDP', 'unisex', 'Amaderada', null, 'perfumes/oud.png', null,
                    ['top' => ['Cardamomo'], 'heart' => [], 'base' => ['Oud']],
                    ['time_of_day' => ['night'], 'climate' => ['cold'], 'occasion' => []],
                    [new ShowcaseVariant(50, 185000)],
                )
                : null;
        }
    };
}

it('shows the perfume with the address of its photo', function () {
    $detail = (new GetShowcasePerfume(detailReader(), new InMemoryFileStorage))->execute(SHOWCASE_PERFUME);

    expect($detail->name)->toBe('Oud Nocturno')
        ->and($detail->photoUrl)->toBe('https://archivos.test/perfumes/oud.png')
        ->and($detail->variants)->toHaveCount(1);
});

it('treats a perfume that the showcase does not show as not found', function () {
    (new GetShowcasePerfume(detailReader(), new InMemoryFileStorage))->execute('0192f0c4-0000-7000-9000-999999999999');
})->throws(ShowcasePerfumeNotFoundException::class, 'catalog.perfume_not_found');

<?php

declare(strict_types=1);

use Src\Catalog\Perfumes\Application\DTOs\ListPerfumesQuery;
use Src\Catalog\Perfumes\Application\UseCases\ListPerfumes;
use Src\Catalog\Perfumes\Application\UseCases\ShowPerfume;
use Src\Catalog\Perfumes\Domain\Entities\Perfume;
use Src\Catalog\Perfumes\Domain\Exceptions\PerfumeNotFoundException;
use Src\Catalog\Perfumes\Domain\ValueObjects\Brand;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeDetails;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeName;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\InMemoryPerfumeRepository;

/**
 * An in-memory repository with fictitious perfumes, one of them inactive.
 *
 * @return array{InMemoryPerfumeRepository, list<Perfume>}
 */
function listedPerfumes(): array
{
    $perfumes = new InMemoryPerfumeRepository;
    $stored = [];

    foreach ([['Tom Ford', 'Oud Wood'], ['Maison Margiela', 'Replica Jazz Club'], ['Tom Ford', 'Tobacco Vanille'], ['Byredo', 'Gypsy Water']] as [$brand, $name]) {
        $perfume = Perfume::create($perfumes->nextId(), new BusinessId('0192f0c4-cccc-7000-9000-000000000001'), new Brand($brand), new PerfumeName($name), PerfumeDetails::empty());
        $stored[] = $perfume;
        $perfumes->save($perfume);
    }

    $stored[3]->deactivate();
    $perfumes->save($stored[3]);

    return [$perfumes, $stored];
}

it('lists the active and the inactive perfumes ordered by brand and name', function () {
    [$perfumes] = listedPerfumes();

    $page = (new ListPerfumes($perfumes))->execute(new ListPerfumesQuery);

    expect(array_map(fn (Perfume $perfume): string => $perfume->brand->value.' '.$perfume->name->value, $page->items))
        ->toBe(['Byredo Gypsy Water', 'Maison Margiela Replica Jazz Club', 'Tom Ford Oud Wood', 'Tom Ford Tobacco Vanille'])
        ->and($page->total)->toBe(4);
});

it('searches the brand and the name ignoring case and blank text means no search', function () {
    [$perfumes] = listedPerfumes();
    $listPerfumes = new ListPerfumes($perfumes);

    expect($listPerfumes->execute(new ListPerfumesQuery('TOM'))->total)->toBe(2)
        ->and($listPerfumes->execute(new ListPerfumesQuery('jazz'))->total)->toBe(1)
        ->and($listPerfumes->execute(new ListPerfumesQuery('   '))->total)->toBe(4)
        ->and($listPerfumes->execute(new ListPerfumesQuery('zzz'))->items)->toBe([]);
});

it('pages the result', function () {
    [$perfumes] = listedPerfumes();

    $second = (new ListPerfumes($perfumes))->execute(new ListPerfumesQuery(page: 2, perPage: 3));

    expect($second->items)->toHaveCount(1)
        ->and($second->total)->toBe(4)
        ->and($second->page)->toBe(2);
});

it('shows a perfume and treats a missing one, or an identity that is not one, as not found', function () {
    [$perfumes, $stored] = listedPerfumes();
    $showPerfume = new ShowPerfume($perfumes);

    expect($showPerfume->execute($stored[0]->id->value))->toBe($stored[0])
        ->and(fn () => $showPerfume->execute('0192f0c4-0000-7000-9000-999999999999'))->toThrow(PerfumeNotFoundException::class)
        ->and(fn () => $showPerfume->execute('no-es-un-uuid'))->toThrow(PerfumeNotFoundException::class);
});

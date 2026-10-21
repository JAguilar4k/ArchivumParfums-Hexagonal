<?php

declare(strict_types=1);

use Src\Catalog\Showcase\Application\DTOs\ShowcaseFilters;
use Src\Catalog\Showcase\Application\UseCases\GetShowcase;
use Tests\Support\Catalog\RecordingShowcaseReader;
use Tests\Support\Shared\InMemoryFileStorage;

it('gives each photo its address and leaves the perfumes without one as they are', function () {
    $page = (new GetShowcase(new RecordingShowcaseReader, new InMemoryFileStorage))->execute(new ShowcaseFilters);

    expect($page->items[0]->photoUrl)->toBe('https://archivos.test/perfumes/oud.png')
        ->and($page->items[1]->photoUrl)->toBeNull()
        ->and($page->total)->toBe(2)
        ->and($page->perPage)->toBe(20);
});

it('passes the filters to the reader as they came', function () {
    $reader = new RecordingShowcaseReader;
    $filters = new ShowcaseFilters(text: 'oud', moment: 'night', maxPrice: 200000, page: 2, perPage: 5);

    (new GetShowcase($reader, new InMemoryFileStorage))->execute($filters);

    expect($reader->received)->toBe($filters);
});

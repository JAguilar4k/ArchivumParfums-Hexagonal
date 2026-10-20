<?php

declare(strict_types=1);

use Src\Catalog\Perfumes\Application\DTOs\EditPerfumeCommand;
use Src\Catalog\Perfumes\Application\UseCases\EditPerfume;
use Src\Catalog\Perfumes\Domain\Entities\Perfume;
use Src\Catalog\Perfumes\Domain\Exceptions\DuplicatePerfumeException;
use Src\Catalog\Perfumes\Domain\Exceptions\PerfumeNotFoundException;
use Src\Catalog\Perfumes\Domain\ValueObjects\Brand;
use Src\Catalog\Perfumes\Domain\ValueObjects\Climate;
use Src\Catalog\Perfumes\Domain\ValueObjects\NoteLayer;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeDetails;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeName;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeNote;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeTag;
use Src\Catalog\Perfumes\Domain\ValueObjects\SizeMl;
use Src\Catalog\Perfumes\Domain\ValueObjects\TimeOfDay;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\InMemoryPerfumeRepository;
use Tests\Support\Shared\RecordingTransactionRunner;

const EDIT_PERFUME_BUSINESS = '0192f0c4-bbbb-7000-9000-000000000001';

/**
 * Wire the use case to an in-memory repository that already holds one perfume with notes, tags and a presentation.
 *
 * @return array{EditPerfume, Perfume, InMemoryPerfumeRepository}
 */
function editPerfumeWiring(): array
{
    $perfumes = new InMemoryPerfumeRepository;
    $perfume = Perfume::create(
        $perfumes->nextId(),
        new BusinessId(EDIT_PERFUME_BUSINESS),
        new Brand('Tom Ford'),
        new PerfumeName('Oud Wood'),
        new PerfumeDetails('EDP', 'unisex', 'Amaderada', null),
        [new PerfumeNote(NoteLayer::Top, 'Cardamomo'), new PerfumeNote(NoteLayer::Base, 'Oud')],
        [PerfumeTag::timeOfDay(TimeOfDay::Night), PerfumeTag::climate(Climate::Cold)],
    );
    $perfume->addVariant($perfumes->nextVariantId(), new SizeMl(5), null, null);
    $perfumes->save($perfume);

    return [new EditPerfume($perfumes, new RecordingTransactionRunner), $perfume, $perfumes];
}

it('changes only the fields sent', function () {
    [$editPerfume, $perfume] = editPerfumeWiring();

    $edited = $editPerfume->execute(new EditPerfumeCommand($perfume->id->value, ['name' => 'Oud Wood Intense']));

    expect($edited->name->value)->toBe('Oud Wood Intense')
        ->and($edited->brand->value)->toBe('Tom Ford')
        ->and($edited->details->concentration)->toBe('EDP')
        ->and($edited->notes)->toHaveCount(2)
        ->and($edited->tags)->toHaveCount(2);
});

it('clears a text that may be missing when it is sent as null', function () {
    [$editPerfume, $perfume] = editPerfumeWiring();

    $edited = $editPerfume->execute(new EditPerfumeCommand($perfume->id->value, ['concentration' => null]));

    expect($edited->details->concentration)->toBeNull()
        ->and($edited->details->family)->toBe('Amaderada');
});

it('replaces the notes of the layers sent and keeps the others', function () {
    [$editPerfume, $perfume] = editPerfumeWiring();

    $edited = $editPerfume->execute(new EditPerfumeCommand($perfume->id->value, ['notes' => ['heart' => ['Rosa'], 'base' => []]]));

    $texts = array_map(fn (PerfumeNote $note): string => $note->layer->value.':'.$note->note, $edited->notes);
    expect($texts)->toBe(['heart:Rosa', 'top:Cardamomo']);
});

it('replaces the tags of the kinds sent and keeps the others', function () {
    [$editPerfume, $perfume] = editPerfumeWiring();

    $edited = $editPerfume->execute(new EditPerfumeCommand($perfume->id->value, ['tags' => ['climate' => ['warm', 'temperate']]]));

    $texts = array_map(fn (PerfumeTag $tag): string => $tag->kind->value.':'.$tag->value, $edited->tags);
    expect($texts)->toBe(['climate:temperate', 'climate:warm', 'time_of_day:night']);
});

it('deactivates and reactivates the perfume without losing its presentations, notes or tags', function () {
    [$editPerfume, $perfume] = editPerfumeWiring();

    $inactive = $editPerfume->execute(new EditPerfumeCommand($perfume->id->value, ['active' => false]));

    expect($inactive->isActive)->toBeFalse()
        ->and($inactive->variants)->toHaveCount(1)
        ->and($inactive->notes)->toHaveCount(2)
        ->and($inactive->tags)->toHaveCount(2);

    expect($editPerfume->execute(new EditPerfumeCommand($perfume->id->value, ['active' => true]))->isActive)->toBeTrue();
});

it('treats a perfume that does not exist, or an identity that is not one, as not found', function (string $id) {
    [$editPerfume] = editPerfumeWiring();

    expect(fn () => $editPerfume->execute(new EditPerfumeCommand($id, ['name' => 'Otro'])))
        ->toThrow(PerfumeNotFoundException::class, 'catalog.perfume_not_found');
})->with([
    'unknown' => '0192f0c4-0000-7000-9000-999999999999',
    'not an identity' => 'no-es-un-uuid',
]);

it('refuses to turn a perfume into one that already exists', function () {
    [$editPerfume, $perfume, $perfumes] = editPerfumeWiring();
    $other = Perfume::create($perfumes->nextId(), new BusinessId(EDIT_PERFUME_BUSINESS), new Brand('Tom Ford'), new PerfumeName('Tobacco Vanille'), PerfumeDetails::empty());
    $perfumes->save($other);

    expect(fn () => $editPerfume->execute(new EditPerfumeCommand($other->id->value, ['name' => 'oud wood', 'concentration' => 'edp'])))
        ->toThrow(DuplicatePerfumeException::class);
});

it('rejects the data the domain cannot hold', function () {
    [$editPerfume, $perfume] = editPerfumeWiring();

    expect(fn () => $editPerfume->execute(new EditPerfumeCommand($perfume->id->value, ['tags' => ['time_of_day' => ['evening']]])))
        ->toThrow(InvalidValueException::class, 'catalog.time_of_day_invalid');
});

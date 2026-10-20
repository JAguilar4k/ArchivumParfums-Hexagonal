<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Catalog\Perfumes\Domain\Contracts\PerfumeRepository;
use Src\Catalog\Perfumes\Domain\Entities\Perfume;
use Src\Catalog\Perfumes\Domain\Entities\PerfumeVariant;
use Src\Catalog\Perfumes\Domain\Exceptions\DuplicateVariantSizeException;
use Src\Catalog\Perfumes\Domain\ValueObjects\Brand;
use Src\Catalog\Perfumes\Domain\ValueObjects\Climate;
use Src\Catalog\Perfumes\Domain\ValueObjects\NoteLayer;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeDetails;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeName;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeNote;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeTag;
use Src\Catalog\Perfumes\Domain\ValueObjects\SizeMl;
use Src\Catalog\Perfumes\Domain\ValueObjects\TimeOfDay;
use Src\Catalog\Perfumes\Infrastructure\PerfumesServiceProvider;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Repositories\EloquentPerfumeRepository;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

/** A repository whose business context answers the business given, as a request of that business would. */
function perfumeRepositoryOf(string $businessId): EloquentPerfumeRepository
{
    $context = new FixedBusinessContext(new BusinessId($businessId));

    return new EloquentPerfumeRepository($context);
}

/** A fictitious perfume with details, notes, tags and two presentations, one of them with a manual price. */
function perfumeToStore(EloquentPerfumeRepository $repository, string $businessId): Perfume
{
    $perfume = Perfume::create(
        $repository->nextId(),
        new BusinessId($businessId),
        new Brand('Tom Ford'),
        new PerfumeName('Oud Wood'),
        new PerfumeDetails('EDP', 'unisex', 'Amaderada', 'Madera de oud con especias.'),
        [new PerfumeNote(NoteLayer::Top, 'Cardamomo'), new PerfumeNote(NoteLayer::Base, 'Oud')],
        [PerfumeTag::timeOfDay(TimeOfDay::Night), PerfumeTag::climate(Climate::Cold), PerfumeTag::occasion('Cena')],
    );
    $perfume->addVariant($repository->nextVariantId(), new SizeMl(5), new Colones(9500), new Colones(10000));
    $perfume->addVariant($repository->nextVariantId(), new SizeMl(10), null, null);

    return $perfume;
}

it('saves a perfume and rebuilds an equal one with its presentations and manual price', function (): void {
    $business = BusinessModel::factory()->create();
    $this->scopeQueriesTo($business->id);
    $repository = perfumeRepositoryOf($business->id);
    $perfume = perfumeToStore($repository, $business->id);

    $repository->save($perfume);

    $found = $repository->find($perfume->id);

    expect($found)->toEqual($perfume)
        ->and($found?->variants[0]->manualPrice?->amount)->toBe(10000)
        ->and($found?->variants[1]->salePrice)->toBeNull();
    assertDatabaseCount('perfume_notes', 2);
    assertDatabaseCount('perfume_tags', 3);
    assertDatabaseCount('perfume_variants', 2);
});

it('deletes the note and the tag that the aggregate dropped and keeps the rest', function (): void {
    $business = BusinessModel::factory()->create();
    $this->scopeQueriesTo($business->id);
    $repository = perfumeRepositoryOf($business->id);
    $perfume = perfumeToStore($repository, $business->id);
    $repository->save($perfume);

    $perfume->revise(
        $perfume->brand,
        $perfume->name,
        $perfume->details,
        [new PerfumeNote(NoteLayer::Base, 'Oud')],
        [PerfumeTag::timeOfDay(TimeOfDay::Night)],
    );
    $repository->save($perfume);

    assertDatabaseCount('perfume_notes', 1);
    assertDatabaseMissing('perfume_notes', ['layer' => 'top', 'note' => 'Cardamomo']);
    assertDatabaseHas('perfume_notes', ['layer' => 'base', 'note' => 'Oud']);
    assertDatabaseCount('perfume_tags', 1);
    assertDatabaseMissing('perfume_tags', ['kind' => 'climate', 'value' => 'cold']);
    expect($repository->find($perfume->id))->toEqual($perfume);
});

it('updates a presentation by its id without deleting or recreating its row', function (): void {
    $business = BusinessModel::factory()->create();
    $this->scopeQueriesTo($business->id);
    $repository = perfumeRepositoryOf($business->id);
    $perfume = perfumeToStore($repository, $business->id);
    $repository->save($perfume);
    $variant = $perfume->variants[0];
    $before = DB::table('perfume_variants')->where('id', $variant->id->value)->sole();

    $perfume->repriceVariant($variant->id, new Colones(9900), null);
    $perfume->deactivateVariant($variant->id);
    $repository->save($perfume);

    $after = DB::table('perfume_variants')->where('id', $variant->id->value)->sole();
    assertDatabaseCount('perfume_variants', 2);
    expect($after->created_at)->toBe($before->created_at)
        ->and($after->sale_price)->toBe(9900)
        ->and($after->sale_price_override)->toBeNull()
        ->and($after->is_active)->toBeFalse();
});

it('finds nothing of another business, exactly as for a perfume that does not exist', function (): void {
    $mine = BusinessModel::factory()->create();
    $other = BusinessModel::factory()->create();
    $this->scopeQueriesTo($other->id);
    $otherRepository = perfumeRepositoryOf($other->id);
    $theirs = perfumeToStore($otherRepository, $other->id);
    $otherRepository->save($theirs);

    // The database is still scoped to the other business, so only the filter of the repository keeps the perfume away.
    $repository = perfumeRepositoryOf($mine->id);

    expect($repository->find($theirs->id))->toBeNull()
        ->and($repository->findForUpdate($theirs->id))->toBeNull()
        ->and($repository->find($repository->nextId()))->toBeNull();

    $this->scopeQueriesTo($mine->id);

    // With the database scoped to the right business, row-level security answers the same.
    expect($repository->find($theirs->id))->toBeNull();
});

it('keeps every write or none when the transaction fails halfway', function (): void {
    $business = BusinessModel::factory()->create();
    $this->scopeQueriesTo($business->id);
    $repository = perfumeRepositoryOf($business->id);
    $perfume = perfumeToStore($repository, $business->id);
    $repository->save($perfume);

    $perfume->revise(
        $perfume->brand,
        new PerfumeName('Nombre nuevo'),
        $perfume->details,
        [...$perfume->notes, new PerfumeNote(NoteLayer::Heart, 'Rosa')],
        $perfume->tags,
    );
    // Larger than the integer column: the presentations fail after the perfume and its notes were written.
    $perfume->repriceVariant($perfume->variants[0]->id, new Colones(3_000_000_000), null);

    expect(fn () => $repository->save($perfume))->toThrow(QueryException::class);

    assertDatabaseHas('perfumes', ['id' => $perfume->id->value, 'name' => 'Oud Wood']);
    assertDatabaseMissing('perfumes', ['name' => 'Nombre nuevo']);
    assertDatabaseMissing('perfume_notes', ['note' => 'Rosa']);
});

it('raises the domain error for two presentations with the same milliliters', function (): void {
    $business = BusinessModel::factory()->create();
    $this->scopeQueriesTo($business->id);
    $repository = perfumeRepositoryOf($business->id);
    $perfume = perfumeToStore($repository, $business->id);
    $repository->save($perfume);

    // Rebuilt as if another process had added a presentation of 5 ml under a different id.
    $clash = Perfume::reconstitute(
        $perfume->id,
        $perfume->businessId,
        $perfume->brand,
        $perfume->name,
        $perfume->details,
        $perfume->notes,
        $perfume->tags,
        true,
        [...$perfume->variants, PerfumeVariant::create($repository->nextVariantId(), new SizeMl(5), null, null)],
    );

    expect(fn () => $repository->save($clash))->toThrow(DuplicateVariantSizeException::class);
});

it('loads the notes, tags and presentations up front instead of one query per row', function (): void {
    $business = BusinessModel::factory()->create();
    $this->scopeQueriesTo($business->id);
    $repository = perfumeRepositoryOf($business->id);
    $perfume = perfumeToStore($repository, $business->id);
    $repository->save($perfume);

    DB::enableQueryLog();
    $repository->find($perfume->id);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toHaveCount(4);
});

it('locks the perfume it returns for update', function (): void {
    $business = BusinessModel::factory()->create();
    $this->scopeQueriesTo($business->id);
    $repository = perfumeRepositoryOf($business->id);
    $perfume = perfumeToStore($repository, $business->id);
    $repository->save($perfume);

    expect($repository->findForUpdate($perfume->id))->toEqual($perfume);
});

it('binds the port to the Eloquent adapter in the service provider of the module', function (): void {
    $business = BusinessModel::factory()->create();
    app()->instance(BusinessContext::class, new FixedBusinessContext(new BusinessId($business->id)));
    app()->register(PerfumesServiceProvider::class);

    expect(app()->make(PerfumeRepository::class))->toBeInstanceOf(EloquentPerfumeRepository::class);
});

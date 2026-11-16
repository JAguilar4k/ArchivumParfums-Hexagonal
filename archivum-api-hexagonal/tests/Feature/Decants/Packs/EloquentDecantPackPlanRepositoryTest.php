<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Decants\Packs\Application\Contracts\DecantSizes;
use Src\Decants\Packs\Application\Contracts\PublicPackPlanReader;
use Src\Decants\Packs\Domain\Contracts\DecantPackPlanRepository;
use Src\Decants\Packs\Domain\Entities\DecantPackPlan;
use Src\Decants\Packs\Domain\Exceptions\DecantPackPlanNotFoundException;
use Src\Decants\Packs\Domain\ValueObjects\DecantCount;
use Src\Decants\Packs\Domain\ValueObjects\MaxPricePerMl;
use Src\Decants\Packs\Domain\ValueObjects\MlPerDecant;
use Src\Decants\Packs\Domain\ValueObjects\PackPrice;
use Src\Decants\Packs\Domain\ValueObjects\PlanName;
use Src\Decants\Packs\Domain\ValueObjects\ValidityDays;
use Src\Decants\Packs\Infrastructure\PacksServiceProvider;
use Src\Decants\Packs\Infrastructure\Persistence\Repositories\EloquentDecantPackPlanRepository;
use Src\Decants\Packs\Infrastructure\Persistence\Repositories\PostgresPublicPackPlanReader;
use Src\Decants\Packs\Infrastructure\Settings\BusinessSettingsDecantSizes;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\assertDatabaseCount;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

/** A repository whose business context answers the business given, as a request of that business would. */
function packPlanRepositoryOf(string $businessId): EloquentDecantPackPlanRepository
{
    return new EloquentDecantPackPlanRepository(new FixedBusinessContext(new BusinessId($businessId)));
}

/** The initial offer of the specification in a business, not saved yet. */
function packPlanToStore(EloquentDecantPackPlanRepository $repository, string $businessId, ?int $cap = 2500): DecantPackPlan
{
    return DecantPackPlan::create($repository->nextId(), new BusinessId($businessId), new PlanName('Descubrimiento'),
        new DecantCount(5), new MlPerDecant(5), new PackPrice(40000), new ValidityDays(180),
        $cap === null ? null : new MaxPricePerMl($cap));
}

it('saves an offer and rebuilds an equal one, with a cap or without it', function (?int $cap) {
    $business = BusinessModel::factory()->create();
    $this->scopeQueriesTo($business->id);
    $repository = packPlanRepositoryOf($business->id);
    $plan = packPlanToStore($repository, $business->id, $cap);

    $repository->save($plan);

    expect($repository->find($plan->id))->toEqual($plan);
})->with(['with a cap' => [2500], 'without a cap' => [null]]);

it('updates the offer by its id, also off sale, without creating another row', function () {
    $business = BusinessModel::factory()->create();
    $this->scopeQueriesTo($business->id);
    $repository = packPlanRepositoryOf($business->id);
    $plan = packPlanToStore($repository, $business->id);
    $repository->save($plan);
    $createdAt = DB::table('decant_pack_plans')->where('id', $plan->id->value)->value('created_at');

    $plan->revise(new PlanName('Diez de 2 ml'), new DecantCount(10), new MlPerDecant(2), new PackPrice(25000), new ValidityDays(90), null);
    $plan->deactivate();
    $repository->save($plan);

    assertDatabaseCount('decant_pack_plans', 1);
    expect($repository->find($plan->id))->toEqual($plan)
        ->and(DB::table('decant_pack_plans')->where('id', $plan->id->value)->value('created_at'))->toBe($createdAt);
});

it('finds and lists nothing of another business, exactly as for an offer that does not exist', function () {
    $mine = BusinessModel::factory()->create();
    $other = BusinessModel::factory()->create();
    $this->scopeQueriesTo($other->id);
    $otherRepository = packPlanRepositoryOf($other->id);
    $theirs = packPlanToStore($otherRepository, $other->id);
    $otherRepository->save($theirs);

    // The database is still scoped to the other business, so only the filter of the repository keeps the offer away.
    $repository = packPlanRepositoryOf($mine->id);

    expect($repository->find($theirs->id))->toBeNull()
        ->and($repository->find($theirs->id, forUpdate: true))->toBeNull()
        ->and($repository->find($repository->nextId()))->toBeNull()
        ->and($repository->all())->toBe([]);

    $this->scopeQueriesTo($mine->id);

    // With the database scoped to the right business, row-level security answers the same.
    expect($repository->find($theirs->id))->toBeNull()
        ->and($repository->all())->toBe([]);
});

it('locks the offer it returns for an update, and only then', function () {
    $business = BusinessModel::factory()->create();
    $this->scopeQueriesTo($business->id);
    $repository = packPlanRepositoryOf($business->id);
    $plan = packPlanToStore($repository, $business->id);
    $repository->save($plan);

    DB::enableQueryLog();
    $locked = $repository->find($plan->id, forUpdate: true);
    $read = $repository->find($plan->id);
    $queries = array_column(DB::getQueryLog(), 'query');
    DB::disableQueryLog();

    expect($locked)->toEqual($plan)
        ->and($read)->toEqual($plan)
        ->and($queries)->toHaveCount(2)
        ->and($queries[0])->toEndWith('for update')
        ->and($queries[1])->not->toContain('for update');
});

it('refuses to write an offer of another business and leaves it as it was, whatever the database is scoped to', function () {
    $a = BusinessModel::factory()->create();
    $b = BusinessModel::factory()->create();
    $this->scopeQueriesTo($b->id);
    $repositoryOfB = packPlanRepositoryOf($b->id);
    $planOfB = packPlanToStore($repositoryOfB, $b->id);
    $repositoryOfB->save($planOfB);

    $planOfB->revise(new PlanName('Cambiada desde A'), new DecantCount(1), new MlPerDecant(2), new PackPrice(1), new ValidityDays(1), null);
    $repositoryOfA = packPlanRepositoryOf($a->id);

    // The database is still scoped to B, so only the own check of the repository keeps the write away.
    expect(fn () => $repositoryOfA->save($planOfB))->toThrow(DecantPackPlanNotFoundException::class, 'packs.plan_not_found')
        ->and($repositoryOfB->find($planOfB->id)?->name->value)->toBe('Descubrimiento');

    // Scoped to A, as a request of A would be, it is refused the same way.
    $this->scopeQueriesTo($a->id);

    expect(fn () => $repositoryOfA->save($planOfB))->toThrow(DecantPackPlanNotFoundException::class, 'packs.plan_not_found');

    $this->scopeQueriesTo($b->id);
    expect($repositoryOfB->find($planOfB->id)?->price->amount)->toBe(40000);
});

it('resolves the ports to their adapters from the normal boot of the application, with nothing registered by the test', function () {
    expect(app()->providerIsLoaded(PacksServiceProvider::class))->toBeTrue()
        ->and(app()->make(DecantPackPlanRepository::class))->toBeInstanceOf(EloquentDecantPackPlanRepository::class)
        ->and(app()->make(PublicPackPlanReader::class))->toBeInstanceOf(PostgresPublicPackPlanReader::class)
        ->and(app()->make(DecantSizes::class))->toBeInstanceOf(BusinessSettingsDecantSizes::class);
});

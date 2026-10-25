<?php

declare(strict_types=1);

use Src\Decants\Bottles\Domain\Exceptions\InsufficientMlException;
use Src\Decants\Bottles\Infrastructure\Persistence\Models\DecantBottleModel;
use Src\Decants\Bottles\Infrastructure\Persistence\Repositories\PostgresMlReservation;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

it('reserves releases and consumes ml with a movement for each atomic update', function () {
    $business = actingAsOwner();
    $bottle = DecantBottleModel::factory()->withRemainingMl(10)->create(['business_id' => $business->business->id]);
    $this->scopeQueriesTo($business->business->id);
    $stock = new PostgresMlReservation(new FixedBusinessContext(new BusinessId($business->business->id)));

    $stock->reserve($bottle->id, 10, userId: $business->owner->id);
    assertDatabaseHas('decant_bottles', ['id' => $bottle->id, 'remaining_ml' => 10, 'reserved_ml' => 10, 'status' => 'open']);
    $stock->release($bottle->id, 5, userId: $business->owner->id);
    $stock->consume($bottle->id, 5, userId: $business->owner->id);
    $stock->reserve($bottle->id, 5, userId: $business->owner->id);
    $stock->consume($bottle->id, 5, userId: $business->owner->id);

    assertDatabaseHas('decant_bottles', ['id' => $bottle->id, 'remaining_ml' => 0, 'reserved_ml' => 0, 'status' => 'empty']);
    assertDatabaseCount('decant_movements', 5);
    expect(fn () => $stock->reserve($bottle->id, 1))->toThrow(InsufficientMlException::class);
    assertDatabaseCount('decant_movements', 5);
});

it('refuses excessive reservations and foreign bottles without a movement', function () {
    $business = actingAsOwner();
    $bottle = DecantBottleModel::factory()->withRemainingMl(5)->create(['business_id' => $business->business->id]);
    $foreign = DecantBottleModel::factory()->create();
    $this->scopeQueriesTo($business->business->id);
    $stock = new PostgresMlReservation(new FixedBusinessContext(new BusinessId($business->business->id)));

    expect(fn () => $stock->reserve($bottle->id, 6))->toThrow(InsufficientMlException::class);
    expect(fn () => $stock->reserve($foreign->id, 1))->toThrow(InsufficientMlException::class);
    expect(fn () => $stock->release($bottle->id, 1))->toThrow(InsufficientMlException::class);
    expect(fn () => $stock->consume($bottle->id, 1))->toThrow(InsufficientMlException::class);
    assertDatabaseCount('decant_movements', 0);
});

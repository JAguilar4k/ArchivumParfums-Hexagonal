<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Src\Businesses\Profile\Domain\Entities\Business;
use Src\Businesses\Profile\Domain\Exceptions\SlugAlreadyInUseException;
use Src\Businesses\Profile\Domain\ValueObjects\AppointmentPlace;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessName;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSettings;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessSlug;
use Src\Businesses\Profile\Domain\ValueObjects\BusinessTimezone;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Repositories\EloquentBusinessRepository;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\assertDatabaseCount;

pest()->use(RefreshDatabaseAsOwner::class);

/** Build a new business with the default settings and a fresh identity. */
function newBusiness(EloquentBusinessRepository $repository, string $slug = 'archivumparfums', ?string $place = 'Escazú, San Rafael'): Business
{
    return Business::create(
        $repository->nextId(),
        new BusinessName('ArchivumParfums'),
        new BusinessSlug($slug),
        new BusinessTimezone(BusinessTimezone::DEFAULT),
        $place === null ? null : new AppointmentPlace($place),
    );
}

/**
 * Read the settings column exactly as PostgreSQL stores it.
 *
 * @return array<string, mixed>
 */
function storedSettings(string $businessId): array
{
    $settings = json_decode((string) DB::table('businesses')->where('id', $businessId)->value('settings'), true);
    assert(is_array($settings));

    return $settings;
}

it('saves a business with its slug, its time zone and its appointment place', function () {
    $repository = new EloquentBusinessRepository;
    $business = newBusiness($repository);

    $repository->save($business);

    $model = BusinessModel::query()->findOrFail($business->id->value);
    expect($model->name)->toBe('ArchivumParfums')
        ->and($model->slug)->toBe('archivumparfums')
        ->and($model->timezone)->toBe('America/Costa_Rica')
        ->and($model->location_text)->toBe('Escazú, San Rafael');
});

it('stores a missing appointment place as null', function () {
    $repository = new EloquentBusinessRepository;
    $business = newBusiness($repository, place: null);

    $repository->save($business);

    expect(BusinessModel::query()->findOrFail($business->id->value)->location_text)->toBeNull();
});

it('stores the default settings as an empty json object', function () {
    $repository = new EloquentBusinessRepository;
    $business = newBusiness($repository);

    $repository->save($business);

    expect(DB::table('businesses')->where('id', $business->id->value)->value('settings'))->toBe('{}');
});

it('stores only the settings that differ from the defaults, in snake_case', function () {
    $repository = new EloquentBusinessRepository;
    $business = newBusiness($repository);

    $repository->save(Business::reconstitute(
        $business->id,
        $business->name,
        $business->slug,
        $business->timezone,
        $business->appointmentPlace,
        new BusinessSettings(interviewMarginColones: 7_000, decantSizesMl: [3, 5]),
    ));

    expect(storedSettings($business->id->value))->toEqual([
        'interview_margin_colones' => 7_000,
        'decant_sizes_ml' => [3, 5],
    ]);
});

it('updates the row of a business saved again instead of adding another', function () {
    $repository = new EloquentBusinessRepository;
    $business = newBusiness($repository);
    $repository->save($business);

    $repository->save(Business::reconstitute(
        $business->id,
        new BusinessName('Archivum Parfums'),
        new BusinessSlug('archivum-parfums'),
        new BusinessTimezone('America/Managua'),
        null,
        $business->settings,
    ));

    assertDatabaseCount('businesses', 1);
    $model = BusinessModel::query()->findOrFail($business->id->value);
    expect($model->name)->toBe('Archivum Parfums')
        ->and($model->slug)->toBe('archivum-parfums')
        ->and($model->timezone)->toBe('America/Managua')
        ->and($model->location_text)->toBeNull();
});

it('raises the domain error for a repeated slug and keeps the transaction usable', function () {
    $repository = new EloquentBusinessRepository;
    $repository->save(newBusiness($repository));

    expect(fn () => $repository->save(newBusiness($repository)))->toThrow(SlugAlreadyInUseException::class);

    assertDatabaseCount('businesses', 1);
});

it('reads a saved business back unchanged by its identity', function () {
    $repository = new EloquentBusinessRepository;
    $business = Business::reconstitute(
        $repository->nextId(),
        new BusinessName('ArchivumParfums'),
        new BusinessSlug('archivumparfums'),
        new BusinessTimezone(BusinessTimezone::DEFAULT),
        new AppointmentPlace('Escazú, San Rafael'),
        new BusinessSettings(interviewMarginColones: 7_000),
    );

    $repository->save($business);

    expect($repository->find($business->id))->toEqual($business);
});

it('reads an absent or empty appointment place as null and saves it back as null', function (?string $place) {
    $id = (string) DB::table('businesses')->insertGetId([
        'name' => 'Tienda sin lugar configurado',
        'slug' => 'sin-lugar',
        'location_text' => $place,
        'created_at' => '2026-10-18 10:00:00',
        'updated_at' => '2026-10-18 10:00:00',
    ]);
    $repository = new EloquentBusinessRepository;

    $business = $repository->find(new BusinessId($id)) ?? throw new RuntimeException('The fixture must exist.');
    $repository->save($business);

    expect($business->appointmentPlace)->toBeNull()
        ->and(DB::table('businesses')->where('id', $id)->value('location_text'))->toBeNull();
})->with(['absent' => null, 'empty text' => '']);

it('finds nothing for an unknown identity', function () {
    $repository = new EloquentBusinessRepository;
    $repository->save(newBusiness($repository));

    expect($repository->find($repository->nextId()))->toBeNull();
});

it('gives every new business a distinct time-ordered uuid', function () {
    $repository = new EloquentBusinessRepository;

    $first = $repository->nextId()->value;
    $second = $repository->nextId()->value;

    expect($first)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
        ->and($second)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
        ->and($first)->not->toBe($second);
});

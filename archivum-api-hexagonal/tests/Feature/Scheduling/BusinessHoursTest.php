<?php

declare(strict_types=1);

use Database\Seeders\BusinessesSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Scheduling\BusinessHours\Infrastructure\Persistence\Models\BusinessHoursModel;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;
use function Pest\Laravel\seed;

uses(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

const HOURS_ENDPOINT = '/api/v1/panel/horario';

beforeEach(function (): void {
    config()->set('archivum.demo_password', 'password');

    TwoBusinesses::create();
});

/**
 * One tramo of opening hours, as the table stores it.
 *
 * @return array<string, mixed>
 */
function openingHoursRow(string $businessId, int $weekday, string $opens, string $closes): array
{
    return [
        'id' => (string) Str::uuid7(),
        'business_id' => $businessId,
        'weekday' => $weekday,
        'opens_at' => $opens,
        'closes_at' => $closes,
    ];
}

/** Sign in the owner of the first business of the two of the tests. */
function ownerOfTheFirstBusiness(): void
{
    TwoBusinesses::actAsOwnerOf(TwoBusinesses::FIRST);
}

it('answers the opening hours of the session business ordered by weekday and opening hour', function (): void {
    $businessId = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($businessId);
    DB::table('business_hours')->insert(openingHoursRow($businessId, 4, '17:00', '20:00'));
    DB::table('business_hours')->insert(openingHoursRow($businessId, 2, '09:00', '13:00'));

    $this->scopeQueriesTo(null);
    ownerOfTheFirstBusiness();

    $response = getJson(HOURS_ENDPOINT);

    $response->assertOk();

    expect($response->json('data.tramos'))->toBe([
        ['dia_semana' => 2, 'inicio' => '09:00', 'fin' => '13:00'],
        ['dia_semana' => 4, 'inicio' => '17:00', 'fin' => '20:00'],
    ]);
});

it('replaces the whole schedule in one transaction', function (): void {
    ownerOfTheFirstBusiness();

    $created = putJson(HOURS_ENDPOINT, ['tramos' => [
        ['dia_semana' => 2, 'inicio' => '17:00', 'fin' => '20:00'],
        ['dia_semana' => 6, 'inicio' => '09:00', 'fin' => '13:00'],
    ]]);

    $created->assertOk();

    expect($created->json('data.tramos'))->toHaveCount(2);

    $replaced = putJson(HOURS_ENDPOINT, ['tramos' => [
        ['dia_semana' => 3, 'inicio' => '10:00', 'fin' => '12:00'],
    ]]);

    $replaced->assertOk();

    $this->scopeQueriesTo(TwoBusinesses::idOf(TwoBusinesses::FIRST));

    expect($replaced->json('data.tramos'))->toBe([['dia_semana' => 3, 'inicio' => '10:00', 'fin' => '12:00']])
        ->and(BusinessHoursModel::query()->where('business_id', TwoBusinesses::idOf(TwoBusinesses::FIRST))->count())->toBe(1);
});

it('keeps the previous schedule when a tramo is refused', function (array $tramos, string $campo): void {
    $businessId = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($businessId);
    DB::table('business_hours')->insert(openingHoursRow($businessId, 2, '09:00', '13:00'));

    $this->scopeQueriesTo(null);
    ownerOfTheFirstBusiness();

    $response = putJson(HOURS_ENDPOINT, ['tramos' => $tramos]);

    $response->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json');

    expect($response->json('type'))->toEndWith('/problemas/datos-invalidos')
        ->and($response->json('errores'))->toHaveKey($campo);

    $this->scopeQueriesTo($businessId);

    expect(DB::table('business_hours')->where('business_id', $businessId)->count())->toBe(1)
        ->and(substr((string) DB::table('business_hours')->where('business_id', $businessId)->sole()->opens_at, 0, 5))->toBe('09:00');
})->with([
    'two overlapping tramos of the same day' => [
        [['dia_semana' => 2, 'inicio' => '09:00', 'fin' => '13:00'], ['dia_semana' => 2, 'inicio' => '12:00', 'fin' => '15:00']],
        'tramos.1.inicio',
    ],
    'a tramo that ends before it starts' => [
        [['dia_semana' => 3, 'inicio' => '18:00', 'fin' => '17:00']],
        'tramos.0.fin',
    ],
    'a weekday outside one to seven' => [
        [['dia_semana' => 8, 'inicio' => '09:00', 'fin' => '10:00']],
        'tramos.0.dia_semana',
    ],
]);

it('leaves the store without opening hours when the list is empty', function (): void {
    $businessId = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($businessId);
    DB::table('business_hours')->insert(openingHoursRow($businessId, 2, '09:00', '13:00'));

    $this->scopeQueriesTo(null);
    ownerOfTheFirstBusiness();

    putJson(HOURS_ENDPOINT, ['tramos' => []])->assertOk()->assertJsonPath('data.tramos', []);

    $this->scopeQueriesTo($businessId);

    expect(DB::table('business_hours')->where('business_id', $businessId)->count())->toBe(0);
});

it('never touches the opening hours of another business', function (): void {
    $own = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $other = TwoBusinesses::idOf(TwoBusinesses::SECOND);
    $this->scopeQueriesTo($other);
    DB::table('business_hours')->insert(openingHoursRow($other, 5, '08:00', '12:00'));

    $this->scopeQueriesTo(null);
    ownerOfTheFirstBusiness();

    putJson(HOURS_ENDPOINT, ['tramos' => [['dia_semana' => 1, 'inicio' => '07:00', 'fin' => '08:00']]])->assertOk();

    $this->scopeQueriesTo($own);
    expect(DB::table('business_hours')->where('business_id', $own)->count())->toBe(1);

    $this->scopeQueriesTo($other);
    expect(DB::table('business_hours')->where('business_id', $other)->count())->toBe(1)
        ->and((int) DB::table('business_hours')->where('business_id', $other)->sole()->weekday)->toBe(5);
});

it('forbids the staff and the customer, and refuses a request without a session', function (): void {
    TwoBusinesses::actAs(TwoBusinesses::ADVISOR);
    $asStaff = getJson(HOURS_ENDPOINT);

    TwoBusinesses::actAs(TwoBusinesses::CUSTOMER);
    $asCustomer = getJson(HOURS_ENDPOINT);

    Auth::forgetGuards();
    $asVisitor = getJson(HOURS_ENDPOINT);

    expect($asStaff->status())->toBe(403)
        ->and($asCustomer->status())->toBe(403)
        ->and($asVisitor->status())->toBe(401);
});

it('seeds the example opening hours of every demo business', function (): void {
    seed(DatabaseSeeder::class);

    foreach ([BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID, BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID] as $businessId) {
        $this->scopeQueriesTo($businessId);

        $hours = BusinessHoursModel::query()
            ->where('business_id', $businessId)
            ->orderBy('weekday')
            ->get()
            ->map(fn (BusinessHoursModel $row): string => $row->weekday.' '.substr((string) $row->opens_at, 0, 5).'-'.substr((string) $row->closes_at, 0, 5))
            ->all();

        expect($hours)->toBe(['2 17:00-20:00', '4 17:00-20:00', '6 09:00-13:00']);
    }
});

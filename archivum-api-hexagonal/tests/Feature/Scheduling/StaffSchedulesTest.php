<?php

declare(strict_types=1);

use Database\Seeders\BusinessesSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Scheduling\StaffSchedules\Infrastructure\Persistence\Models\StaffScheduleModel;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;
use function Pest\Laravel\seed;

uses(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

beforeEach(function (): void {
    config()->set('archivum.demo_password', 'password');

    TwoBusinesses::create();
});

/** Add a person of the staff to a business and answer her identifier. */
function staffMemberOf(string $businessId, bool $attendsAppointments = true): string
{
    return MemberModel::factory()->staff()->create([
        'business_id' => $businessId,
        'user_id' => UserModel::factory()->create()->id,
        'attends_appointments' => $attendsAppointments,
    ])->id;
}

/**
 * One tramo of working hours, as the table stores it.
 *
 * @return array<string, mixed>
 */
function staffScheduleRow(string $businessId, string $memberId, int $weekday, string $starts, string $ends): array
{
    return [
        'id' => (string) Str::uuid7(),
        'business_id' => $businessId,
        'member_id' => $memberId,
        'weekday' => $weekday,
        'starts_at' => $starts,
        'ends_at' => $ends,
    ];
}

/** Sign in the owner of the first business of the two of the tests. */
function ownerOfTheFirstBusinessAgain(): void
{
    TwoBusinesses::actAsOwnerOf(TwoBusinesses::FIRST);
}

it('answers the working hours of one person ordered by weekday and starting hour', function (): void {
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($first);
    $member = staffMemberOf($first);
    DB::table('staff_schedules')->insert(staffScheduleRow($first, $member, 4, '17:00', '20:00'));
    DB::table('staff_schedules')->insert(staffScheduleRow($first, $member, 2, '09:00', '13:00'));

    $this->scopeQueriesTo(null);
    ownerOfTheFirstBusinessAgain();

    $response = getJson('/api/v1/panel/personal/'.$member.'/horario');

    $response->assertOk();

    expect($response->json('data.tramos'))->toBe([
        ['dia_semana' => 2, 'inicio' => '09:00', 'fin' => '13:00'],
        ['dia_semana' => 4, 'inicio' => '17:00', 'fin' => '20:00'],
    ]);
});

it('replaces the whole schedule of that person in one transaction', function (): void {
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($first);
    $member = staffMemberOf($first);

    $this->scopeQueriesTo(null);
    ownerOfTheFirstBusinessAgain();

    putJson('/api/v1/panel/personal/'.$member.'/horario', ['tramos' => [
        ['dia_semana' => 2, 'inicio' => '17:00', 'fin' => '20:00'],
        ['dia_semana' => 6, 'inicio' => '09:00', 'fin' => '13:00'],
    ]])->assertOk()->assertJsonCount(2, 'data.tramos');

    $replaced = putJson('/api/v1/panel/personal/'.$member.'/horario', ['tramos' => [
        ['dia_semana' => 3, 'inicio' => '10:00', 'fin' => '12:00'],
    ]]);

    $replaced->assertOk();

    $this->scopeQueriesTo($first);

    expect($replaced->json('data.tramos'))->toBe([['dia_semana' => 3, 'inicio' => '10:00', 'fin' => '12:00']])
        ->and(StaffScheduleModel::query()->where('business_id', $first)->where('member_id', $member)->count())->toBe(1);
});

it('answers 409 conflicto for a person who does not attend appointments and writes nothing', function (): void {
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($first);
    $member = staffMemberOf($first, attendsAppointments: false);

    $this->scopeQueriesTo(null);
    ownerOfTheFirstBusinessAgain();

    $response = putJson('/api/v1/panel/personal/'.$member.'/horario', ['tramos' => [
        ['dia_semana' => 2, 'inicio' => '17:00', 'fin' => '20:00'],
    ]]);

    $response->assertStatus(409)
        ->assertHeader('Content-Type', 'application/problem+json');

    expect($response->json('type'))->toEndWith('/problemas/conflicto')
        ->and($response->json('detail'))->toBe('Esa persona todavía no atiende citas.');

    $this->scopeQueriesTo($first);

    expect(StaffScheduleModel::query()->where('member_id', $member)->count())->toBe(0);
});

it('answers a person of another business exactly like one that does not exist', function (): void {
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $second = TwoBusinesses::idOf(TwoBusinesses::SECOND);
    $this->scopeQueriesTo($second);
    $foreign = staffMemberOf($second);

    $this->scopeQueriesTo(null);
    ownerOfTheFirstBusinessAgain();

    $unknown = '019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6f70';
    $foreignResponse = getJson('/api/v1/panel/personal/'.$foreign.'/horario');
    $missingResponse = getJson('/api/v1/panel/personal/'.$unknown.'/horario');
    $foreignWrite = putJson('/api/v1/panel/personal/'.$foreign.'/horario', ['tramos' => [
        ['dia_semana' => 2, 'inicio' => '08:00', 'fin' => '09:00'],
    ]]);

    expect($foreignResponse->status())->toBe(404)
        ->and($missingResponse->status())->toBe(404)
        ->and($foreignResponse->json('type'))->toBe($missingResponse->json('type'))
        ->and($foreignResponse->json('detail'))->toBe($missingResponse->json('detail'))
        ->and($foreignWrite->status())->toBe(404);

    $this->scopeQueriesTo($second);

    expect(StaffScheduleModel::query()->where('member_id', $foreign)->count())->toBe(0);
});

it('keeps the previous schedule of the person when a tramo is refused', function (array $tramos, string $campo): void {
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($first);
    $member = staffMemberOf($first);
    DB::table('staff_schedules')->insert(staffScheduleRow($first, $member, 2, '09:00', '13:00'));

    $this->scopeQueriesTo(null);
    ownerOfTheFirstBusinessAgain();

    $response = putJson('/api/v1/panel/personal/'.$member.'/horario', ['tramos' => $tramos]);

    $response->assertStatus(422);

    expect($response->json('errores'))->toHaveKey($campo);

    $this->scopeQueriesTo($first);

    expect(StaffScheduleModel::query()->where('member_id', $member)->count())->toBe(1)
        ->and(substr((string) StaffScheduleModel::query()->where('member_id', $member)->sole()->starts_at, 0, 5))->toBe('09:00');
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
        [['dia_semana' => 0, 'inicio' => '09:00', 'fin' => '10:00']],
        'tramos.0.dia_semana',
    ],
]);

it('forbids the staff, even on her own hours, and the customer, and refuses a request without a session', function (): void {
    $first = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $this->scopeQueriesTo($first);
    $member = staffMemberOf($first);

    $this->scopeQueriesTo(null);

    TwoBusinesses::actAs(TwoBusinesses::ADVISOR);
    $asStaff = getJson('/api/v1/panel/personal/'.$member.'/horario');

    TwoBusinesses::actAs(TwoBusinesses::CUSTOMER);
    $asCustomer = getJson('/api/v1/panel/personal/'.$member.'/horario');

    Auth::forgetGuards();
    $asVisitor = getJson('/api/v1/panel/personal/'.$member.'/horario');

    expect($asStaff->status())->toBe(403)
        ->and($asCustomer->status())->toBe(403)
        ->and($asVisitor->status())->toBe(401);
});

it('seeds the example hours of every demo person who attends appointments', function (): void {
    seed(DatabaseSeeder::class);

    foreach ([BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID, BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID] as $businessId) {
        $this->scopeQueriesTo($businessId);

        $attending = MemberModel::query()
            ->where('business_id', $businessId)
            ->where('attends_appointments', true)
            ->pluck('id')
            ->all();

        expect($attending)->not->toBeEmpty();

        foreach ($attending as $memberId) {
            $horas = StaffScheduleModel::query()
                ->where('business_id', $businessId)
                ->where('member_id', $memberId)
                ->orderBy('weekday')
                ->get()
                ->map(fn (StaffScheduleModel $row): string => $row->weekday.' '.substr((string) $row->starts_at, 0, 5).'-'.substr((string) $row->ends_at, 0, 5))
                ->all();

            expect($horas)->toBe(['2 17:00-20:00', '4 17:00-20:00', '6 09:00-13:00']);
        }
    }
});

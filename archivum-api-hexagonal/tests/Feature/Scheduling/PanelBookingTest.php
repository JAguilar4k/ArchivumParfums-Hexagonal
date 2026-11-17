<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeModel;
use Src\Scheduling\BusinessHours\Infrastructure\Persistence\Models\BusinessHoursModel;
use Src\Scheduling\Services\Infrastructure\Persistence\Models\ServiceModel;
use Src\Scheduling\StaffSchedules\Infrastructure\Persistence\Models\StaffScheduleModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\postJson;

uses(RefreshDatabaseAsOwner::class);

/** Pin the transaction of the test to the business, as the middleware of the request does. */
function panelBookScopeTo(string $businessId): void
{
    app(BusinessTransaction::class)->scope($businessId);
}

/**
 * A store of the seeded staff, open from 09:00 to 13:00, whose two people attend appointments, with a 60 minute
 * service and three perfumes.
 *
 * @return array{staffed: StaffedBusiness, service: ServiceModel, day: DateTimeImmutable, first: string, second: string, customer: string, perfumes: list<string>}
 */
function panelBookScenario(): array
{
    $staffed = StaffedBusiness::create();
    $staffed->business->update(['location_text' => 'Escazú, frente al parque']);
    $staffed->owner->update(['name' => 'Ana Solís']);
    $staffed->staff->update(['name' => 'Beto Rojas']);

    panelBookScopeTo($staffed->business->id);

    $day = (new DateTimeImmutable('now', new DateTimeZone((string) config('app.timezone'))))->setTime(0, 0)->modify('+7 days');
    $weekday = (int) $day->format('N');

    $service = ServiceModel::factory()->create([
        'business_id' => $staffed->business->id,
        'name' => 'Prueba guiada',
        'duration_minutes' => 60,
        'buffer_minutes' => 15,
        'max_perfumes' => 3,
        'is_active' => true,
    ]);

    BusinessHoursModel::factory()->create([
        'business_id' => $staffed->business->id,
        'weekday' => $weekday,
        'opens_at' => '09:00',
        'closes_at' => '13:00',
    ]);

    $members = [];

    foreach ([$staffed->owner, $staffed->staff] as $user) {
        $member = MemberModel::query()
            ->where('business_id', $staffed->business->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        StaffScheduleModel::factory()->create([
            'business_id' => $staffed->business->id,
            'member_id' => $member->id,
            'weekday' => $weekday,
            'starts_at' => '09:00',
            'ends_at' => '13:00',
        ]);

        $members[] = $member->id;
    }

    $perfumes = [];

    for ($index = 0; $index < 3; $index++) {
        $perfumes[] = PerfumeModel::factory()->create(['business_id' => $staffed->business->id])->id;
    }

    return [
        'staffed' => $staffed,
        'service' => $service,
        'day' => $day,
        'first' => $members[0],
        'second' => $members[1],
        'customer' => (string) DB::table('customers')
            ->where('business_id', $staffed->business->id)
            ->where('user_id', $staffed->customer->id)
            ->value('id'),
        'perfumes' => $perfumes,
    ];
}

/** The instant of an hour of the day of the scenario, in ISO 8601 with the offset of the store. */
function panelBookAt(DateTimeImmutable $day, string $time): string
{
    [$hours, $minutes] = array_map(intval(...), explode(':', $time));

    return $day->setTime($hours, $minutes)->format(DATE_ATOM);
}

/**
 * The body of a panel booking at the given hour, for the customer of the scenario unless another is given.
 *
 * @param  array{staffed: StaffedBusiness, service: ServiceModel, day: DateTimeImmutable, first: string, second: string, customer: string, perfumes: list<string>}  $scenario
 * @param  list<string>|null  $perfumes
 * @return array<string, mixed>
 */
function panelBookBody(array $scenario, string $at = '09:00', ?string $customer = null, ?string $service = null, ?array $perfumes = null, ?string $person = null): array
{
    return array_filter([
        'cliente_id' => $customer ?? $scenario['customer'],
        'servicio_id' => $service ?? $scenario['service']->id,
        'inicia_en' => panelBookAt($scenario['day'], $at),
        'persona_id' => $person,
        'perfumes' => $perfumes ?? [$scenario['perfumes'][0]],
    ], fn (mixed $value): bool => $value !== null);
}

/**
 * Book an active appointment of the store, so another one competes with it.
 *
 * @param  array{staffed: StaffedBusiness, service: ServiceModel, day: DateTimeImmutable, first: string, second: string, customer: string, perfumes: list<string>}  $scenario
 */
function panelBookCompetitor(array $scenario, string $memberId, string $from, string $to, string $blockedUntil): void
{
    panelBookScopeTo($scenario['staffed']->business->id);

    DB::table('appointments')->insert([
        'id' => (string) Str::uuid7(),
        'business_id' => $scenario['staffed']->business->id,
        'service_id' => $scenario['service']->id,
        'customer_id' => $scenario['customer'],
        'member_id' => $memberId,
        'starts_at' => $scenario['day']->setTime(...array_map(intval(...), explode(':', $from))),
        'ends_at' => $scenario['day']->setTime(...array_map(intval(...), explode(':', $to))),
        'blocked_until' => $scenario['day']->setTime(...array_map(intval(...), explode(':', $blockedUntil))),
        'status' => 'confirmed',
    ]);
}

test('books a confirmed appointment in the name of the customer of the store', function (): void {
    $scenario = panelBookScenario();
    actingAsOwner($scenario['staffed']);

    $response = postJson('/api/v1/panel/citas', [
        ...panelBookBody($scenario, '09:00', perfumes: $scenario['perfumes']),
        // A store named in the body never changes the store of the session.
        'tienda' => 'otra-tienda',
        'business_id' => (string) Str::uuid7(),
    ])->assertCreated();

    $response->assertJsonPath('data.estado', 'confirmada')
        ->assertJsonPath('data.persona.nombre', 'Ana Solís')
        ->assertJsonPath('data.inicia_en', panelBookAt($scenario['day'], '09:00'))
        ->assertJsonCount(3, 'data.perfumes');

    panelBookScopeTo($scenario['staffed']->business->id);

    $row = (array) DB::table('appointments')->where('id', (string) $response->json('data.id'))->firstOrFail();

    expect($row['business_id'])->toBe($scenario['staffed']->business->id)
        ->and($row['customer_id'])->toBe($scenario['customer'])
        ->and($row['status'])->toBe('confirmed')
        ->and(DB::table('appointment_perfumes')->where('appointment_id', $row['id'])->count())->toBe(3);
});

test('assigns the person with the fewest appointments that day, as the customer booking does', function (): void {
    $scenario = panelBookScenario();
    panelBookCompetitor($scenario, $scenario['first'], '09:00', '10:00', '10:15');

    actingAsOwner($scenario['staffed']);

    postJson('/api/v1/panel/citas', panelBookBody($scenario, '10:30'))
        ->assertCreated()
        ->assertJsonPath('data.persona.id', $scenario['second'])
        ->assertJsonPath('data.persona.nombre', 'Beto Rojas');
});

test('answers a conflict when the slot of the chosen person is already taken', function (): void {
    $scenario = panelBookScenario();
    panelBookCompetitor($scenario, $scenario['first'], '10:00', '11:00', '11:15');

    actingAsOwner($scenario['staffed']);

    postJson('/api/v1/panel/citas', panelBookBody($scenario, '10:00', person: $scenario['first']))
        ->assertConflict()
        ->assertJsonPath('detail', 'Ese horario ya no está disponible.');
});

test('rejects a customer, a service, a person and a perfume of another store', function (): void {
    $scenario = panelBookScenario();
    $other = panelBookScenario();

    $foreignCustomer = CustomerModel::factory()->create([
        'business_id' => $other['staffed']->business->id,
        'user_id' => UserModel::factory()->create()->id,
    ])->id;

    $notAttending = MemberModel::factory()->create([
        'business_id' => $scenario['staffed']->business->id,
        'user_id' => UserModel::factory()->create()->id,
        'attends_appointments' => false,
    ])->id;

    actingAsOwner($scenario['staffed']);

    postJson('/api/v1/panel/citas', panelBookBody($scenario, customer: $foreignCustomer))
        ->assertUnprocessable()
        ->assertJsonPath('errores.cliente_id.0', __('scheduling.booking.customer_unavailable'));

    postJson('/api/v1/panel/citas', panelBookBody($scenario, customer: (string) Str::uuid7()))
        ->assertUnprocessable()
        ->assertJsonPath('errores.cliente_id.0', __('scheduling.booking.customer_unavailable'));

    postJson('/api/v1/panel/citas', panelBookBody($scenario, service: $other['service']->id))
        ->assertUnprocessable()
        ->assertJsonPath('errores.servicio_id.0', __('scheduling.booking.service_unavailable'));

    postJson('/api/v1/panel/citas', panelBookBody($scenario, person: $notAttending))
        ->assertUnprocessable()
        ->assertJsonPath('errores.persona_id.0', __('scheduling.booking.person_unavailable'));

    postJson('/api/v1/panel/citas', panelBookBody($scenario, perfumes: [$other['perfumes'][0]]))
        ->assertUnprocessable()
        ->assertJsonPath('errores.perfumes.0', __('scheduling.booking.perfume_unavailable'));
});

test('rejects more perfumes than the service allows and an inactive service', function (): void {
    $scenario = panelBookScenario();
    $extra = PerfumeModel::factory()->create(['business_id' => $scenario['staffed']->business->id])->id;

    actingAsOwner($scenario['staffed']);

    postJson('/api/v1/panel/citas', panelBookBody($scenario, perfumes: [...$scenario['perfumes'], $extra]))
        ->assertUnprocessable()
        ->assertJsonPath('errores.perfumes.0', __('scheduling.appointment.too_many_perfumes', ['max' => 3]));

    $scenario['service']->update(['is_active' => false]);

    postJson('/api/v1/panel/citas', panelBookBody($scenario))
        ->assertUnprocessable()
        ->assertJsonPath('errores.servicio_id.0', __('scheduling.booking.service_unavailable'));
});

test('refuses the booking to the staff and to a customer of the store', function (): void {
    $scenario = panelBookScenario();

    actingAsStaff($scenario['staffed']);
    postJson('/api/v1/panel/citas', panelBookBody($scenario))->assertForbidden();

    actingAsCustomer($scenario['staffed']);
    postJson('/api/v1/panel/citas', panelBookBody($scenario))->assertForbidden();

    auth()->forgetGuards();
    postJson('/api/v1/panel/citas', panelBookBody($scenario))->assertUnauthorized();
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Scheduling\BusinessHours\Infrastructure\Persistence\Models\BusinessHoursModel;
use Src\Scheduling\ScheduleBlocks\Infrastructure\Persistence\Models\ScheduleBlockModel;
use Src\Scheduling\Services\Infrastructure\Persistence\Models\ServiceModel;
use Src\Scheduling\StaffSchedules\Infrastructure\Persistence\Models\StaffScheduleModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\getJson;

uses(RefreshDatabaseAsOwner::class);

/** Pin the transaction of the test to the business, as the middleware of the request does. */
function scopeAvailabilityTo(string $businessId): void
{
    app(BusinessTransaction::class)->scope($businessId);
}

/** Time zone of the store the scenario belongs to. */
function availabilityZone(): DateTimeZone
{
    return new DateTimeZone((string) config('app.timezone'));
}

/** Start of the local day the test asks for, a week ahead unless the test says otherwise. */
function availabilityDay(int $daysAhead = 7): DateTimeImmutable
{
    return (new DateTimeImmutable('now', availabilityZone()))->setTime(0, 0)->modify("+{$daysAhead} days");
}

/**
 * A store open in one tramo, with one service and the people who attend appointments that weekday.
 *
 * @param  array<string, mixed>  $settings
 * @return array{business: BusinessModel, service: ServiceModel, day: DateTimeImmutable, weekday: int, first: string, second: string, customer: string}
 */
function availabilityScenario(
    string $opensAt = '09:00',
    string $closesAt = '12:00',
    int $durationMinutes = 60,
    int $bufferMinutes = 0,
    array $settings = [],
    ?string $memberOpensAt = null,
    ?string $memberClosesAt = null,
): array {
    $business = BusinessModel::factory()->create(['settings' => (object) $settings]);
    scopeAvailabilityTo($business->id);

    $day = availabilityDay();
    $weekday = (int) $day->format('N');

    $service = ServiceModel::factory()->create([
        'business_id' => $business->id,
        'duration_minutes' => $durationMinutes,
        'buffer_minutes' => $bufferMinutes,
        'max_perfumes' => 3,
        'is_active' => true,
    ]);

    BusinessHoursModel::factory()->create([
        'business_id' => $business->id,
        'weekday' => $weekday,
        'opens_at' => $opensAt,
        'closes_at' => $closesAt,
    ]);

    $first = availabilityMember($business, $weekday, 'Ana Solís', $memberOpensAt ?? $opensAt, $memberClosesAt ?? $closesAt);
    $second = availabilityMember($business, $weekday, 'Beto Rojas', $memberOpensAt ?? $opensAt, $memberClosesAt ?? $closesAt);

    return [
        'business' => $business,
        'service' => $service,
        'day' => $day,
        'weekday' => $weekday,
        'first' => $first,
        'second' => $second,
        'customer' => CustomerModel::factory()->create(['business_id' => $business->id])->id,
    ];
}

/** One person of the staff who attends appointments and works one tramo of that weekday. */
function availabilityMember(BusinessModel $business, int $weekday, string $name, string $from, string $to): string
{
    $user = UserModel::factory()->create(['name' => $name]);
    $member = MemberModel::factory()->create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'attends_appointments' => true,
    ]);

    StaffScheduleModel::factory()->create([
        'business_id' => $business->id,
        'member_id' => $member->id,
        'weekday' => $weekday,
        'starts_at' => $from,
        'ends_at' => $to,
    ]);

    return $member->id;
}

/**
 * The URL of the availability of a service on the day of the scenario, optionally for one person.
 *
 * @param  array{business: BusinessModel, service: ServiceModel, day: DateTimeImmutable, weekday: int, first: string, second: string, customer: string}  $scenario
 */
function availabilityUrl(array $scenario, ?string $serviceId = null, DateTimeImmutable|string|null $day = null, ?string $person = null, ?string $slug = null): string
{
    $date = $day ?? $scenario['day'];

    $query = http_build_query(array_filter([
        'servicio' => $serviceId ?? $scenario['service']->id,
        'fecha' => $date instanceof DateTimeImmutable ? $date->format('Y-m-d') : $date,
        'persona' => $person,
    ], fn (mixed $value): bool => $value !== null));

    return '/api/v1/tiendas/'.($slug ?? $scenario['business']->slug).'/disponibilidad?'.$query;
}

/** The hour of the day of the scenario, as the connection stores the instants. */
function availabilityInstant(DateTimeImmutable $day, string $time): string
{
    [$hours, $minutes] = array_map(intval(...), explode(':', $time));
    $connection = new DateTimeZone((string) config('database.connections.pgsql.timezone', 'UTC'));

    return $day->setTime($hours, $minutes)->setTimezone($connection)->format('Y-m-d H:i:s');
}

/**
 * Insert an appointment of the scenario, with the range it keeps blocked, as the repository of #60 does.
 *
 * @param  array{business: BusinessModel, service: ServiceModel, day: DateTimeImmutable, weekday: int, first: string, second: string, customer: string}  $scenario
 */
function availabilityAppointment(array $scenario, string $memberId, string $from, string $to, string $blockedUntil, string $status = 'confirmed'): void
{
    scopeAvailabilityTo($scenario['business']->id);

    DB::table('appointments')->insert([
        'id' => (string) Str::uuid7(),
        'business_id' => $scenario['business']->id,
        'service_id' => $scenario['service']->id,
        'customer_id' => $scenario['customer'],
        'member_id' => $memberId,
        'starts_at' => availabilityInstant($scenario['day'], $from),
        'ends_at' => availabilityInstant($scenario['day'], $to),
        'blocked_until' => availabilityInstant($scenario['day'], $blockedUntil),
        'status' => $status,
        'cancelled_at' => $status === 'cancelled' ? availabilityInstant($scenario['day'], $from) : null,
        'cancel_reason' => $status === 'cancelled' ? 'Avisó con tiempo' : null,
    ]);
}

/**
 * Block the agenda of one person, or of the whole store when the member is null, inside the day of the scenario.
 *
 * @param  array{business: BusinessModel, service: ServiceModel, day: DateTimeImmutable, weekday: int, first: string, second: string, customer: string}  $scenario
 */
function availabilityBlock(array $scenario, ?string $memberId, string $from, string $to): void
{
    scopeAvailabilityTo($scenario['business']->id);

    ScheduleBlockModel::factory()->create([
        'business_id' => $scenario['business']->id,
        'member_id' => $memberId,
        'starts_at' => availabilityInstant($scenario['day'], $from),
        'ends_at' => availabilityInstant($scenario['day'], $to),
        'reason' => 'Reservado para una reparación',
    ]);
}

/**
 * The hours the endpoint answered, as HH:MM of the store.
 *
 * @param  array{data: list<array{inicia_en: string, termina_en: string, personas: list<array{id: string, nombre: string}>}>}  $response
 * @return list<string>
 */
function availabilityHours(array $response): array
{
    return array_map(
        fn (array $slot): string => (new DateTimeImmutable($slot['inicia_en']))->format('H:i'),
        $response['data'],
    );
}

/**
 * The names of the people of one slot.
 *
 * @param  array{data: list<array{inicia_en: string, termina_en: string, personas: list<array{id: string, nombre: string}>}>}  $response
 * @return list<string>
 */
function availabilityNames(array $response, int $slot): array
{
    return array_map(fn (array $person): string => $person['nombre'], $response['data'][$slot]['personas']);
}

test('lists the free slots of the day and the people who can attend each one', function (): void {
    $scenario = availabilityScenario();
    $response = getJson(availabilityUrl($scenario))->assertOk();
    $json = $response->json();

    expect(availabilityHours($json))->toBe(['09:00', '09:15', '09:30', '09:45', '10:00', '10:15', '10:30', '10:45', '11:00'])
        ->and($json['data'][0]['inicia_en'])->toBe($scenario['day']->modify('+9 hours')->format(DATE_ATOM))
        ->and($json['data'][0]['termina_en'])->toBe($scenario['day']->modify('+10 hours')->format(DATE_ATOM))
        ->and(availabilityNames($json, 0))->toBe(['Ana Solís', 'Beto Rojas'])
        ->and($json['data'][0]['personas'][0]['id'])->toBe($scenario['first']);
});

test('offers the slots of every tramo of the day', function (): void {
    $scenario = availabilityScenario();

    BusinessHoursModel::factory()->create([
        'business_id' => $scenario['business']->id,
        'weekday' => $scenario['weekday'],
        'opens_at' => '14:00',
        'closes_at' => '17:00',
    ]);
    StaffScheduleModel::query()->update(['ends_at' => '17:00']);

    $json = getJson(availabilityUrl($scenario))->assertOk()->json();

    expect(availabilityHours($json))->toContain('09:00', '11:00', '14:00', '16:00');
});

test('keeps the slots inside the intersection of the store and the person', function (): void {
    $scenario = availabilityScenario('08:00', '17:00', 60, 0, [], '09:00', '11:00');

    $json = getJson(availabilityUrl($scenario))->assertOk()->json();

    expect(availabilityHours($json))->toBe(['09:00', '09:15', '09:30', '09:45', '10:00']);
});

test('reserves the margin of an active appointment of the person', function (): void {
    $scenario = availabilityScenario('09:00', '12:00', 30, 15);
    availabilityAppointment($scenario, $scenario['first'], '10:00', '10:30', '10:45');

    $json = getJson(availabilityUrl($scenario))->assertOk()->json();

    expect(availabilityNames($json, 0))->toBe(['Ana Solís', 'Beto Rojas'])
        ->and(availabilityHours($json)[1])->toBe('09:15')
        ->and(availabilityNames($json, 1))->toBe(['Ana Solís', 'Beto Rojas'])
        ->and(availabilityNames($json, 2))->toBe(['Beto Rojas'])
        ->and(availabilityNames($json, 6))->toBe(['Beto Rojas'])
        ->and(availabilityNames($json, 7))->toBe(['Ana Solís', 'Beto Rojas']);
});

test('takes out the blocks of one person and the blocks of the whole store', function (): void {
    $scenario = availabilityScenario();
    availabilityBlock($scenario, $scenario['first'], '09:00', '10:00');
    availabilityBlock($scenario, null, '11:00', '12:00');

    $json = getJson(availabilityUrl($scenario))->assertOk()->json();

    expect(availabilityHours($json))->toBe(['09:00', '09:15', '09:30', '09:45', '10:00'])
        ->and(availabilityNames($json, 0))->toBe(['Beto Rojas'])
        ->and(availabilityNames($json, 4))->toBe(['Ana Solís', 'Beto Rojas']);
});

test('frees the slot again when the appointment that blocked it was cancelled', function (): void {
    $scenario = availabilityScenario();
    availabilityAppointment($scenario, $scenario['first'], '09:00', '10:00', '10:00', 'cancelled');

    $json = getJson(availabilityUrl($scenario))->assertOk()->json();

    expect(availabilityHours($json))->toContain('09:00')
        ->and(availabilityNames($json, 0))->toBe(['Ana Solís', 'Beto Rojas']);
});

test('starts no slot before the notice the store gives', function (): void {
    $scenario = availabilityScenario(settings: ['minimum_notice_hours' => 300]);

    $json = getJson(availabilityUrl($scenario))->assertOk()->json();

    expect($json['data'])->toBe([]);
});

test('offers nothing on a day beyond the booking horizon and still answers 200', function (): void {
    $scenario = availabilityScenario();

    $json = getJson(availabilityUrl($scenario, day: availabilityDay(31)))->assertOk()->json();

    expect($json['data'])->toBe([]);
});

test('answers the slots of the day a person works even when another person of the staff is free', function (): void {
    $scenario = availabilityScenario();

    $json = getJson(availabilityUrl($scenario, person: $scenario['second']))->assertOk()->json();

    expect(availabilityNames($json, 0))->toBe(['Beto Rojas']);
});

test('answers an empty list for a person without a tramo that day', function (): void {
    $scenario = availabilityScenario();
    $user = UserModel::factory()->create(['name' => 'Carla Vega']);
    $member = MemberModel::factory()->create([
        'business_id' => $scenario['business']->id,
        'user_id' => $user->id,
        'attends_appointments' => true,
    ]);

    $json = getJson(availabilityUrl($scenario, person: $member->id))->assertOk()->json();

    expect($json['data'])->toBe([]);
});

test('rejects an inactive service and one of another store with the detail of the field', function (): void {
    $scenario = availabilityScenario();
    $scenario['service']->update(['is_active' => false]);

    getJson(availabilityUrl($scenario))->assertUnprocessable()
        ->assertJsonPath('errores.servicio.0', __('scheduling.availability.service_not_bookable'));

    $other = availabilityScenario();
    scopeAvailabilityTo($scenario['business']->id);

    getJson(availabilityUrl($scenario, serviceId: $other['service']->id))->assertUnprocessable()
        ->assertJsonPath('errores.servicio.0', __('scheduling.availability.service_not_bookable'));
});

test('rejects a person of another store and one who does not attend appointments', function (): void {
    $scenario = availabilityScenario();
    $other = availabilityScenario();
    scopeAvailabilityTo($scenario['business']->id);

    getJson(availabilityUrl($scenario, person: $other['first']))->assertUnprocessable()
        ->assertJsonPath('errores.persona.0', __('scheduling.availability.person_not_available'));

    $user = UserModel::factory()->create(['name' => 'Dora Méndez']);
    $member = MemberModel::factory()->create([
        'business_id' => $scenario['business']->id,
        'user_id' => $user->id,
        'attends_appointments' => false,
    ]);

    getJson(availabilityUrl($scenario, person: $member->id))->assertUnprocessable()
        ->assertJsonPath('errores.persona.0', __('scheduling.availability.person_not_available'));
});

test('validates the query string of the availability', function (): void {
    $scenario = availabilityScenario();

    getJson('/api/v1/tiendas/'.$scenario['business']->slug.'/disponibilidad')->assertUnprocessable()
        ->assertJsonPath('errores.servicio.0', 'El campo servicio es obligatorio.')
        ->assertJsonPath('errores.fecha.0', 'El campo fecha es obligatorio.');

    $response = getJson(availabilityUrl($scenario, day: 'no-es-una-fecha'))->assertUnprocessable();

    expect($response->json('errores.fecha.0'))->toContain('formato');
});

test('answers not found for a store that does not exist', function (): void {
    $scenario = availabilityScenario();

    getJson(availabilityUrl($scenario, slug: 'perfumeria-inexistente'))->assertNotFound()
        ->assertJsonPath('status', 404);
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
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
function scopeBookingTo(string $businessId): void
{
    app(BusinessTransaction::class)->scope($businessId);
}

/** Time zone of the store the scenario belongs to. */
function bookingZone(): DateTimeZone
{
    return new DateTimeZone((string) config('app.timezone'));
}

/** Start of the local day the scenario books, a week ahead. */
function bookingDay(): DateTimeImmutable
{
    return (new DateTimeImmutable('now', bookingZone()))->setTime(0, 0)->modify('+7 days');
}

/**
 * A store with an active service, two people who attend appointments and two perfumes, with its customer signed in.
 *
 * @param  array<string, mixed>  $settings
 * @param  array{duration?: int, buffer?: int, max?: int}  $service
 * @return array{business: BusinessModel, service: ServiceModel, day: DateTimeImmutable, first: string, second: string, customer: string, perfumes: list<string>, staffed: StaffedBusiness}
 */
function bookingScenario(array $settings = [], array $service = []): array
{
    $staffed = actingAsCustomer();
    $business = $staffed->business;
    $business->update(['location_text' => 'Escazú, frente al parque', 'settings' => (object) $settings]);
    $staffed->owner->update(['name' => 'Ana Solís']);
    $staffed->staff->update(['name' => 'Beto Rojas']);

    scopeBookingTo($business->id);

    $day = bookingDay();
    $weekday = (int) $day->format('N');

    $bookable = ServiceModel::factory()->create([
        'business_id' => $business->id,
        'name' => 'Prueba guiada',
        'duration_minutes' => $service['duration'] ?? 60,
        'buffer_minutes' => $service['buffer'] ?? 15,
        'max_perfumes' => $service['max'] ?? 2,
        'is_active' => true,
    ]);

    BusinessHoursModel::factory()->create([
        'business_id' => $business->id,
        'weekday' => $weekday,
        'opens_at' => '09:00',
        'closes_at' => '12:00',
    ]);

    $first = MemberModel::query()->where('business_id', $business->id)->where('user_id', $staffed->owner->id)->firstOrFail();
    $second = MemberModel::query()->where('business_id', $business->id)->where('user_id', $staffed->staff->id)->firstOrFail();

    foreach ([$first, $second] as $member) {
        StaffScheduleModel::factory()->create([
            'business_id' => $business->id,
            'member_id' => $member->id,
            'weekday' => $weekday,
            'starts_at' => '09:00',
            'ends_at' => '12:00',
        ]);
    }

    return [
        'business' => $business,
        'service' => $bookable,
        'day' => $day,
        'first' => $first->id,
        'second' => $second->id,
        'customer' => CustomerModel::query()->where('business_id', $business->id)->where('user_id', $staffed->customer->id)->firstOrFail()->id,
        'perfumes' => [
            PerfumeModel::factory()->create(['business_id' => $business->id])->id,
            PerfumeModel::factory()->create(['business_id' => $business->id])->id,
        ],
        'staffed' => $staffed,
    ];
}

/**
 * The URL that books an appointment in the store of the scenario.
 *
 * @param  array{business: BusinessModel, service: ServiceModel, day: DateTimeImmutable, first: string, second: string, customer: string, perfumes: list<string>, staffed: StaffedBusiness}  $scenario
 */
function bookingUrl(array $scenario): string
{
    return '/api/v1/tiendas/'.$scenario['business']->slug.'/citas';
}

/**
 * The body of a booking at the given hour of the day of the scenario.
 *
 * @param  array{business: BusinessModel, service: ServiceModel, day: DateTimeImmutable, first: string, second: string, customer: string, perfumes: list<string>, staffed: StaffedBusiness}  $scenario
 * @param  list<string>|null  $perfumes
 * @return array<string, mixed>
 */
function bookingBody(array $scenario, string $at = '09:00', ?string $person = null, ?array $perfumes = null, ?string $service = null): array
{
    return array_filter([
        'servicio_id' => $service ?? $scenario['service']->id,
        'inicia_en' => bookingInstant($scenario['day'], $at),
        'persona_id' => $person,
        'perfumes' => $perfumes ?? $scenario['perfumes'],
    ], fn (mixed $value): bool => $value !== null);
}

/** The instant of an hour of the day of the scenario, in ISO 8601 with the offset of the store. */
function bookingInstant(DateTimeImmutable $day, string $time): string
{
    [$hours, $minutes] = array_map(intval(...), explode(':', $time));

    return $day->setTime($hours, $minutes)->format(DATE_ATOM);
}

/**
 * Insert an active appointment of the scenario, so another one competes with it.
 *
 * @param  array{business: BusinessModel, service: ServiceModel, day: DateTimeImmutable, first: string, second: string, customer: string, perfumes: list<string>, staffed: StaffedBusiness}  $scenario
 */
function bookingCompetitor(array $scenario, string $memberId, string $from, string $to, string $blockedUntil): void
{
    scopeBookingTo($scenario['business']->id);

    DB::table('appointments')->insert([
        'id' => (string) Str::uuid7(),
        'business_id' => $scenario['business']->id,
        'service_id' => $scenario['service']->id,
        'customer_id' => $scenario['customer'],
        'member_id' => $memberId,
        'starts_at' => $scenario['day']->setTime(...array_map(intval(...), explode(':', $from))),
        'ends_at' => $scenario['day']->setTime(...array_map(intval(...), explode(':', $to))),
        'blocked_until' => $scenario['day']->setTime(...array_map(intval(...), explode(':', $blockedUntil))),
        'status' => 'confirmed',
    ]);
}

test('books a confirmed appointment of the customer and answers where to read it', function (): void {
    $scenario = bookingScenario();
    $response = postJson(bookingUrl($scenario), bookingBody($scenario))->assertCreated();

    $id = $response->json('data.id');

    $response->assertJsonPath('data.estado', 'confirmada')
        ->assertJsonPath('data.servicio.nombre', 'Prueba guiada')
        ->assertJsonPath('data.persona.nombre', 'Ana Solís')
        ->assertJsonPath('data.inicia_en', bookingInstant($scenario['day'], '09:00'))
        ->assertJsonPath('data.termina_en', bookingInstant($scenario['day'], '10:00'))
        ->assertJsonPath('data.lugar_cita', 'Escazú, frente al parque')
        ->assertJsonCount(2, 'data.perfumes');

    expect($response->headers->get('Location'))->toBe(url('/api/v1/cuenta/citas/'.$id));

    scopeBookingTo($scenario['business']->id);

    $row = DB::table('appointments')->where('id', $id)->first();

    expect($row?->status)->toBe('confirmed')
        ->and($row?->customer_id)->toBe($scenario['customer'])
        ->and($row?->member_id)->toBe($scenario['first'])
        ->and(DB::table('appointment_perfumes')->where('appointment_id', $id)->count())->toBe(2);
});

test('answers a conflict when the slot was already taken by another booking', function (): void {
    $scenario = bookingScenario();

    postJson(bookingUrl($scenario), bookingBody($scenario, person: $scenario['first']))->assertCreated();
    postJson(bookingUrl($scenario), bookingBody($scenario, person: $scenario['first']))->assertConflict()
        ->assertJsonPath('detail', 'Ese horario ya no está disponible.');

    // Another person is still free on that slot, so the store keeps offering it.
    postJson(bookingUrl($scenario), bookingBody($scenario, person: $scenario['second']))->assertCreated();

    scopeBookingTo($scenario['business']->id);

    expect(DB::table('appointments')->count())->toBe(2);
});

test('assigns the person of the slot with the fewest appointments that day', function (): void {
    $scenario = bookingScenario();
    bookingCompetitor($scenario, $scenario['first'], '09:00', '10:00', '10:15');

    $response = postJson(bookingUrl($scenario), bookingBody($scenario, at: '10:30'))->assertCreated();

    expect($response->json('data.persona.id'))->toBe($scenario['second'])
        ->and($response->json('data.persona.nombre'))->toBe('Beto Rojas');
});

test('rejects more perfumes than the service allows', function (): void {
    $scenario = bookingScenario();
    $third = PerfumeModel::factory()->create(['business_id' => $scenario['business']->id])->id;

    postJson(bookingUrl($scenario), bookingBody($scenario, perfumes: [...$scenario['perfumes'], $third]))
        ->assertUnprocessable()
        ->assertJsonPath('errores.perfumes.0', __('scheduling.appointment.too_many_perfumes', ['max' => 2]));
});

test('rejects an inactive perfume and one of another store', function (): void {
    $scenario = bookingScenario();
    $inactive = PerfumeModel::factory()->create(['business_id' => $scenario['business']->id, 'is_active' => false])->id;

    postJson(bookingUrl($scenario), bookingBody($scenario, perfumes: [$inactive]))
        ->assertUnprocessable()
        ->assertJsonPath('errores.perfumes.0', __('scheduling.booking.perfume_unavailable'));

    $other = bookingScenario();

    postJson(bookingUrl($scenario), bookingBody($scenario, perfumes: [$other['perfumes'][0]]))
        ->assertUnprocessable()
        ->assertJsonPath('errores.perfumes.0', __('scheduling.booking.perfume_unavailable'));
});

test('rejects an inactive service and one of another store', function (): void {
    $scenario = bookingScenario();
    $scenario['service']->update(['is_active' => false]);

    postJson(bookingUrl($scenario), bookingBody($scenario))
        ->assertUnprocessable()
        ->assertJsonPath('errores.servicio_id.0', __('scheduling.booking.service_unavailable'));

    $other = bookingScenario();

    postJson(bookingUrl($scenario), bookingBody($scenario, service: $other['service']->id))
        ->assertUnprocessable()
        ->assertJsonPath('errores.servicio_id.0', __('scheduling.booking.service_unavailable'));
});

test('rejects a person of another store and one who does not attend appointments', function (): void {
    $scenario = bookingScenario();
    $other = bookingScenario();

    postJson(bookingUrl($scenario), bookingBody($scenario, person: $other['first']))
        ->assertUnprocessable()
        ->assertJsonPath('errores.persona_id.0', __('scheduling.booking.person_unavailable'));

    scopeBookingTo($scenario['business']->id);

    $user = UserModel::factory()->create(['name' => 'Dora Méndez']);
    $member = MemberModel::factory()->create([
        'business_id' => $scenario['business']->id,
        'user_id' => $user->id,
        'attends_appointments' => false,
    ]);

    postJson(bookingUrl($scenario), bookingBody($scenario, person: $member->id))
        ->assertUnprocessable()
        ->assertJsonPath('errores.persona_id.0', __('scheduling.booking.person_unavailable'));
});

test('rejects a start without offset and one outside the booking window', function (): void {
    $scenario = bookingScenario();

    postJson(bookingUrl($scenario), [...bookingBody($scenario), 'inicia_en' => $scenario['day']->format('Y-m-d').' 09:00:00'])
        ->assertUnprocessable()
        ->assertJsonStructure(['errores' => ['inicia_en']]);

    postJson(bookingUrl($scenario), [...bookingBody($scenario), 'inicia_en' => $scenario['day']->modify('+31 days')->setTime(9, 0)->format(DATE_ATOM)])
        ->assertUnprocessable()
        ->assertJsonPath('errores.inicia_en.0', __('scheduling.booking.outside_window'));

    // A notice longer than the week the scenario books: the slot is still a valid date, but it is too soon.
    $noticed = bookingScenario(settings: ['minimum_notice_hours' => 300]);

    postJson(bookingUrl($noticed), bookingBody($noticed))
        ->assertUnprocessable()
        ->assertJsonPath('errores.inicia_en.0', __('scheduling.booking.outside_window'));
});

test('creates the customer record of the account the first time it books in the store', function (): void {
    $scenario = bookingScenario();
    $user = UserModel::factory()->create(['name' => 'Elena Vargas']);
    Sanctum::actingAs($user, Ability::namesGrantedTo(AccountRole::Customer));

    $response = postJson(bookingUrl($scenario), bookingBody($scenario))->assertCreated();

    scopeBookingTo($scenario['business']->id);

    $customer = CustomerModel::query()->where('business_id', $scenario['business']->id)->where('user_id', $user->id)->first();

    expect($customer)->not->toBeNull();

    $row = DB::table('appointments')->where('id', $response->json('data.id'))->first();
    expect($row?->customer_id)->toBe($customer?->id);
});

test('refuses a booking without a session and one of the staff of the store', function (): void {
    $scenario = bookingScenario();
    auth()->forgetGuards();

    postJson(bookingUrl($scenario), bookingBody($scenario))->assertUnauthorized();

    actingAsStaff($scenario['staffed']);

    postJson(bookingUrl($scenario), bookingBody($scenario))->assertForbidden();
});

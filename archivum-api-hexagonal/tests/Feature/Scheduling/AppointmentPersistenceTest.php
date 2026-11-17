<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeModel;
use Src\Scheduling\Appointments\Domain\Contracts\AppointmentRepository;
use Src\Scheduling\Appointments\Domain\Entities\Appointment;
use Src\Scheduling\Appointments\Domain\Exceptions\AppointmentSlotTakenException;
use Src\Scheduling\Appointments\Domain\ValueObjects\AppointmentId;
use Src\Scheduling\Appointments\Domain\ValueObjects\AppointmentStatus;
use Src\Scheduling\Appointments\Domain\ValueObjects\CustomerId;
use Src\Scheduling\Appointments\Domain\ValueObjects\PerfumeId;
use Src\Scheduling\Appointments\Domain\ValueObjects\ServiceId;
use Src\Scheduling\Services\Infrastructure\Persistence\Models\ServiceModel;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\MemberId;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\Catalog\FixedBusinessContext;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

uses(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

/** Pin the transaction of the test to the business, as the middleware of a request does. */
function scopeAppointmentTestTo(string $businessId): void
{
    app(BusinessTransaction::class)->scope($businessId);
}

/** Time zone of the store, which is the one the connection reads and writes its instants in. */
function appointmentTestZone(): DateTimeZone
{
    return new DateTimeZone((string) config('app.timezone'));
}

/**
 * Create a business with a bookable service, a customer, a person who attends appointments and two perfumes.
 *
 * @return array{business: string, service: string, customer: string, member: string, perfumes: list<string>}
 */
function appointmentRepositoryScenario(): array
{
    $business = BusinessModel::factory()->create();
    scopeAppointmentTestTo($business->id);

    return [
        'business' => $business->id,
        'service' => ServiceModel::factory()->create([
            'business_id' => $business->id,
            'duration_minutes' => 45,
            'buffer_minutes' => 15,
            'max_perfumes' => 3,
        ])->id,
        'customer' => CustomerModel::factory()->create(['business_id' => $business->id])->id,
        'member' => MemberModel::factory()->create([
            'business_id' => $business->id,
            'attends_appointments' => true,
        ])->id,
        'perfumes' => [
            PerfumeModel::factory()->create(['business_id' => $business->id])->id,
            PerfumeModel::factory()->create(['business_id' => $business->id])->id,
        ],
    ];
}

/**
 * Resolve the repository of the container, bound to the business the test names, so the binding of the provider
 * is the one under test.
 *
 * @param  array{business: string, service: string, customer: string, member: string, perfumes: list<string>}  $ids
 */
function appointmentRepository(array $ids): AppointmentRepository
{
    app()->instance(BusinessContext::class, new FixedBusinessContext(new BusinessId($ids['business'])));

    return app()->make(AppointmentRepository::class);
}

/**
 * Build the appointment the tests store: 45 minutes of service, 15 of margin and the perfumes the test chose.
 *
 * @param  array{business: string, service: string, customer: string, member: string, perfumes: list<string>}  $ids
 * @param  list<string>|null  $perfumeIds
 */
function appointmentToPersist(array $ids, string $startsAt = '2026-12-01 10:00:00', ?array $perfumeIds = null): Appointment
{
    $perfumes = array_map(
        fn (string $perfume): PerfumeId => new PerfumeId($perfume),
        $perfumeIds ?? $ids['perfumes'],
    );

    return Appointment::book(
        new AppointmentId((string) Str::uuid7()),
        new BusinessId($ids['business']),
        new ServiceId($ids['service']),
        new CustomerId($ids['customer']),
        new MemberId($ids['member']),
        new DateTimeImmutable($startsAt, appointmentTestZone()),
        45,
        15,
        $perfumes,
        3,
    );
}

test('stores the appointment with its perfumes and rebuilds the aggregate from the rows', function (): void {
    $ids = appointmentRepositoryScenario();
    $repository = appointmentRepository($ids);
    $appointment = appointmentToPersist($ids);
    scopeAppointmentTestTo($ids['business']);

    $repository->save($appointment);

    $this->scopeQueriesTo($ids['business']);

    expect(DB::table('appointments')->count())->toBe(1)
        ->and(DB::table('appointment_perfumes')->count())->toBe(2)
        ->and(DB::table('appointments')->value('status'))->toBe('confirmed');

    $rebuilt = $repository->find($appointment->id);

    expect($rebuilt)->not->toBeNull()
        ->and($rebuilt?->id->value)->toBe($appointment->id->value)
        ->and($rebuilt?->businessId->value)->toBe($ids['business'])
        ->and($rebuilt?->startsAt()->getTimestamp())->toBe($appointment->startsAt()->getTimestamp())
        ->and($rebuilt?->endsAt()->getTimestamp())->toBe($appointment->startsAt()->modify('+45 minutes')->getTimestamp())
        ->and($rebuilt?->blockedUntil()->getTimestamp())->toBe($appointment->startsAt()->modify('+60 minutes')->getTimestamp())
        ->and($rebuilt?->status())->toBe(AppointmentStatus::Confirmed)
        ->and(array_map(fn (PerfumeId $perfume): string => $perfume->value, $rebuilt?->perfumes() ?? []))
        ->toEqualCanonicalizing($ids['perfumes']);
});

test('updates the stored appointment instead of inserting it again', function (): void {
    $ids = appointmentRepositoryScenario();
    $repository = appointmentRepository($ids);
    $appointment = appointmentToPersist($ids);
    scopeAppointmentTestTo($ids['business']);

    $repository->save($appointment);

    $appointment->reschedule(
        new DateTimeImmutable('2026-12-02 15:00:00', appointmentTestZone()),
        new DateTimeImmutable('2026-12-01 08:00:00', appointmentTestZone()),
        2,
    );
    $repository->save($appointment);

    $rebuilt = $repository->find($appointment->id);

    $this->scopeQueriesTo($ids['business']);

    expect($rebuilt?->startsAt()->format('Y-m-d H:i'))->toBe('2026-12-02 15:00')
        ->and($rebuilt?->blockedUntil()->format('Y-m-d H:i'))->toBe('2026-12-02 16:00')
        ->and(DB::table('appointments')->count())->toBe(1)
        ->and(DB::table('appointment_perfumes')->count())->toBe(2);
});

test('does not find an appointment of another business', function (): void {
    $ids = appointmentRepositoryScenario();
    $repository = appointmentRepository($ids);
    $appointment = appointmentToPersist($ids);
    scopeAppointmentTestTo($ids['business']);

    $repository->save($appointment);

    $other = appointmentRepositoryScenario();
    $otherRepository = appointmentRepository($other);

    expect($otherRepository->find($appointment->id))->toBeNull();

    scopeAppointmentTestTo($ids['business']);

    expect($repository->find($appointment->id))->not->toBeNull();
});

test('refuses to store an appointment of another business', function (): void {
    $ids = appointmentRepositoryScenario();
    $other = appointmentRepositoryScenario();
    $repository = appointmentRepository($ids);
    scopeAppointmentTestTo($ids['business']);

    expect(fn () => $repository->save(appointmentToPersist($other)))
        ->toThrow(InvalidValueException::class);
});

test('translates the exclusion violation of the slot into a conflict of the domain', function (): void {
    $ids = appointmentRepositoryScenario();
    $repository = appointmentRepository($ids);
    scopeAppointmentTestTo($ids['business']);

    $repository->save(appointmentToPersist($ids));

    // 10:30 starts inside the range the first appointment blocks, 10:00 to 11:00.
    $exception = null;

    try {
        $repository->save(appointmentToPersist($ids, '2026-12-01 10:30:00'));
    } catch (AppointmentSlotTakenException $raised) {
        $exception = $raised;
    }

    expect($exception)->toBeInstanceOf(AppointmentSlotTakenException::class);
    assert($exception instanceof AppointmentSlotTakenException);

    expect(__($exception->translationKey(), $exception->parameters()))->toBe('Ese horario ya no está disponible.');

    $this->scopeQueriesTo($ids['business']);

    expect(DB::table('appointments')->count())->toBe(1);
});

test('keeps any other database error as it is and rolls the appointment of the failed transaction back', function (): void {
    $ids = appointmentRepositoryScenario();
    $other = appointmentRepositoryScenario();
    $repository = appointmentRepository($ids);
    scopeAppointmentTestTo($ids['business']);

    // The perfume belongs to another business, so the composite foreign key of appointment_perfumes rejects it.
    $appointment = appointmentToPersist($ids, '2026-12-01 10:00:00', [$other['perfumes'][0]]);

    expect(fn () => $repository->save($appointment))->toThrow(QueryException::class);

    $this->scopeQueriesTo($ids['business']);

    expect(DB::table('appointments')->count())->toBe(0)
        ->and(DB::table('appointment_perfumes')->count())->toBe(0);
});

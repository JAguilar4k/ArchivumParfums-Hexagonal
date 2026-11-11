<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ParallelProcesses;

use function Pest\Laravel\postJson;

pest()->use(RefreshDatabaseAsOwner::class);

/**
 * A store with one service, one person who attends appointments and two perfumes, committed with the schema owner so
 * the processes of the race can see it, and the token of its customer.
 *
 * @return array{business: string, slug: string, service: string, member: string, customer: string, day: DateTimeImmutable, perfumes: list<string>, token: string, tables: list<string>}
 */
function committedBookingScenario(int $duration = 60, int $buffer = 15): array
{
    $owner = DB::connection('pgsql_admin');
    $timestamps = ['created_at' => now(), 'updated_at' => now()];

    $businessId = (string) Str::uuid7();
    $userId = (string) Str::uuid7();
    $customerId = (string) Str::uuid7();
    $memberId = (string) Str::uuid7();
    $serviceId = (string) Str::uuid7();
    $perfumeIds = [(string) Str::uuid7(), (string) Str::uuid7()];
    $plainToken = 'fictitious-booking-token-'.Str::uuid7();
    $slug = 'concurrent-booking-'.Str::uuid7();
    $day = (new DateTimeImmutable('now', new DateTimeZone((string) config('app.timezone'))))->setTime(0, 0)->modify('+7 days');
    $weekday = (int) $day->format('N');

    $tokenId = $owner->transaction(function () use ($owner, $timestamps, $businessId, $userId, $customerId, $memberId, $serviceId, $perfumeIds, $plainToken, $slug, $weekday, $duration, $buffer): int {
        $owner->table('businesses')->insert(['id' => $businessId, 'name' => 'Tienda ficticia', 'slug' => $slug, 'timezone' => 'America/Costa_Rica', 'settings' => '{}', ...$timestamps]);
        $owner->statement("select set_config('app.business_id', ?, true)", [$businessId]);

        $owner->table('users')->insert(['id' => $userId, 'name' => 'Cliente ficticio', 'email' => $slug.'@archivum.test', 'password' => 'unused-fictitious-hash', ...$timestamps]);
        $owner->table('customers')->insert(['id' => $customerId, 'business_id' => $businessId, 'user_id' => $userId, ...$timestamps]);
        $owner->table('members')->insert(['id' => $memberId, 'business_id' => $businessId, 'user_id' => $userId, 'role' => 'staff', 'attends_appointments' => true, ...$timestamps]);
        $owner->table('services')->insert(['id' => $serviceId, 'business_id' => $businessId, 'name' => 'Prueba concurrente', 'duration_minutes' => $duration, 'price' => 5000, 'buffer_minutes' => $buffer, 'max_perfumes' => 3, 'is_active' => true, ...$timestamps]);
        $owner->table('business_hours')->insert(['id' => (string) Str::uuid7(), 'business_id' => $businessId, 'weekday' => $weekday, 'opens_at' => '09:00', 'closes_at' => '12:00', ...$timestamps]);
        $owner->table('staff_schedules')->insert(['id' => (string) Str::uuid7(), 'business_id' => $businessId, 'member_id' => $memberId, 'weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '12:00', ...$timestamps]);

        foreach ($perfumeIds as $perfumeId) {
            $owner->table('perfumes')->insert(['id' => $perfumeId, 'business_id' => $businessId, 'brand' => 'Ficticia', 'name' => 'Aroma '.Str::uuid7(), 'is_active' => true, ...$timestamps]);
        }

        $owner->table('personal_access_tokens')->insert(['tokenable_type' => UserModel::class, 'tokenable_id' => $userId, 'name' => 'concurrency-test', 'token' => hash('sha256', $plainToken), 'abilities' => '["*"]', ...$timestamps]);

        return (int) $owner->table('personal_access_tokens')->where('token', hash('sha256', $plainToken))->value('id');
    });

    return [
        'business' => $businessId,
        'slug' => $slug,
        'service' => $serviceId,
        'member' => $memberId,
        'customer' => $customerId,
        'day' => $day,
        'perfumes' => $perfumeIds,
        'token' => $tokenId.'|'.$plainToken,
        'tables' => ['appointments', 'appointment_perfumes', 'staff_schedules', 'business_hours', 'services', 'perfumes', 'members', 'customers', 'personal_access_tokens', 'users', 'businesses'],
    ];
}

it('R14: accepts one of two simultaneous bookings of the same slot and leaves no half-written appointment', function (): void {
    $scenario = committedBookingScenario();
    $body = [
        'servicio_id' => $scenario['service'],
        'inicia_en' => $scenario['day']->setTime(9, 0)->format(DATE_ATOM),
        'persona_id' => $scenario['member'],
        'perfumes' => $scenario['perfumes'],
    ];

    // Each process boots the app, sends the same booking and commits on its own connection: only the exclusion
    // constraint of #59 can decide which one wins. Without it both would answer 201 and this test would fail.
    $book = function () use ($scenario, $body): array {
        $kernel = app(Kernel::class);
        $request = Request::create('/api/v1/tiendas/'.$scenario['slug'].'/citas', 'POST', [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$scenario['token'],
            ],
            json_encode($body, JSON_THROW_ON_ERROR));

        try {
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);

            return ['status' => $response->getStatusCode(), 'body' => (string) $response->getContent()];
        } catch (Throwable $error) {
            return ['status' => 0, 'body' => $error::class.': '.$error->getMessage()];
        }
    };

    $results = inParallel($scenario['tables'], $book, $book);

    expect(array_column($results, 'status'))->toEqualCanonicalizing([201, 409]);

    $owner = DB::connection('pgsql_admin');

    $owner->transaction(function () use ($owner, $scenario): void {
        $owner->statement("select set_config('app.business_id', ?, true)", [$scenario['business']]);

        expect($owner->table('appointments')->count())->toBe(1)
            ->and($owner->table('appointment_perfumes')->count())->toBe(2);
    });
});

it('answers a conflict when the booking starts inside the margin of another active appointment', function (): void {
    $scenario = committedBookingScenario(duration: 30, buffer: 30);
    // @phpstan-ignore method.notFound (the proxy forwards the call to the running test case)
    $this->beforeApplicationDestroyed(fn () => ParallelProcesses::emptyTables($scenario['tables']));

    $token = $scenario['token'];

    // The first appointment lasts 30 minutes and keeps 30 more blocked: [09:00, 10:00).
    postJson('/api/v1/tiendas/'.$scenario['slug'].'/citas', [
        'servicio_id' => $scenario['service'],
        'inicia_en' => $scenario['day']->setTime(9, 0)->format(DATE_ATOM),
        'persona_id' => $scenario['member'],
        'perfumes' => $scenario['perfumes'],
    ], ['Authorization' => 'Bearer '.$token])->assertCreated();

    // 09:45 does not cross the duration of the first appointment, but it starts inside its margin.
    postJson('/api/v1/tiendas/'.$scenario['slug'].'/citas', [
        'servicio_id' => $scenario['service'],
        'inicia_en' => $scenario['day']->setTime(9, 45)->format(DATE_ATOM),
        'persona_id' => $scenario['member'],
        'perfumes' => $scenario['perfumes'],
    ], ['Authorization' => 'Bearer '.$token])
        ->assertConflict()
        ->assertJsonPath('detail', 'Ese horario ya no está disponible.');

    // The two requests of the test share its transaction, so the appointment it wrote is read the same way.
    app(BusinessTransaction::class)->scope($scenario['business']);

    expect(DB::table('appointments')->count())->toBe(1);
});

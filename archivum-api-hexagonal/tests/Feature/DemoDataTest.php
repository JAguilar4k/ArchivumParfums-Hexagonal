<?php

declare(strict_types=1);

use Database\Seeders\BusinessesSeeder;
use Database\Seeders\CustomersSeeder;
use Database\Seeders\StaffSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

beforeEach(function () {
    config(['archivum.demo_password' => 'clave-de-demostracion']);
});

/**
 * Staff of a demo business by email: role and whether the person attends appointments. The test pins the business first.
 *
 * @return array<string, array{string, bool}>
 */
function demoStaff(string $slug): array
{
    return DB::table('members')
        ->join('businesses', 'businesses.id', '=', 'members.business_id')
        ->join('users', 'users.id', '=', 'members.user_id')
        ->where('businesses.slug', $slug)
        ->orderBy('users.email')
        ->get(['users.email', 'members.role', 'members.attends_appointments'])
        ->mapWithKeys(fn (object $row): array => [$row->email => [$row->role, (bool) $row->attends_appointments]])
        ->all();
}

/**
 * Customers of a demo business by email, with their phone. The test pins the business first.
 *
 * @return array<string, string>
 */
function demoCustomers(string $slug): array
{
    return DB::table('customers')
        ->join('businesses', 'businesses.id', '=', 'customers.business_id')
        ->join('users', 'users.id', '=', 'customers.user_id')
        ->where('businesses.slug', $slug)
        ->orderBy('users.email')
        ->pluck('customers.phone', 'users.email')
        ->all();
}

it('seeds the two demo businesses with fixed identifiers and the default settings', function () {
    seed();

    expect(DB::table('businesses')->orderBy('slug')->pluck('name', 'slug')->all())->toBe([
        'archivumparfums' => 'ArchivumParfums',
        'esencias-del-norte' => 'Esencias del Norte',
    ])->and(DB::table('businesses')->orderBy('slug')->pluck('id', 'slug')->all())->toBe([
        'archivumparfums' => BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID,
        'esencias-del-norte' => BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID,
    ])->and(DB::table('businesses')->pluck('timezone')->unique()->all())->toBe(['America/Costa_Rica'])
        ->and(DB::table('businesses')->pluck('settings')->unique()->all())->toBe(['{}']);
});

it('seeds the owner, the co-owner and an advisor who attends appointments in ArchivumParfums', function () {
    seed();
    $this->scopeQueriesTo(BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID);

    expect(demoStaff('archivumparfums'))->toBe([
        'asesor@archivumparfums.test' => ['staff', true],
        'coduena@archivumparfums.test' => ['owner', false],
        'duena@archivumparfums.test' => ['owner', true],
    ])->and(demoCustomers('archivumparfums'))->toBe([
        'carlos@clientes.test' => '+50688880002',
        'mariana@clientes.test' => '+50688880001',
        'sofia@clientes.test' => '+50688880003',
    ]);
});

it('seeds a second business with its owner and customers, one of them shared', function () {
    seed();
    $this->scopeQueriesTo(BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID);

    expect(demoStaff('esencias-del-norte'))->toBe([
        'dueno@esenciasdelnorte.test' => ['owner', true],
    ])->and(demoCustomers('esencias-del-norte'))->toBe([
        'lucia@clientes.test' => '+50688880004',
        'sofia@clientes.test' => '+50688880003',
    ]);
    assertDatabaseCount('users', 8);
});

it('uses only fictitious emails and test phones', function () {
    seed();

    foreach (DB::table('users')->pluck('email') as $email) {
        expect($email)->toEndWith('.test');
    }

    foreach ([BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID, BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID] as $businessId) {
        $this->scopeQueriesTo($businessId);

        foreach (DB::table('customers')->pluck('phone') as $phone) {
            expect($phone)->toStartWith('+5068888000');
        }
    }
});

it('gives every demo account the password of the configuration', function () {
    seed();

    foreach (UserModel::query()->get() as $user) {
        expect(Hash::check('clave-de-demostracion', $user->password))->toBeTrue();
    }
});

it('lets every demo account sign in with the password of the configuration', function () {
    seed();

    foreach (DB::table('users')->pluck('email') as $email) {
        postJson('/api/v1/sesiones', ['correo' => $email, 'contrasena' => 'clave-de-demostracion', 'dispositivo' => 'Computadora'])->assertCreated();
    }
});

it('keeps the same rows when it is seeded again', function () {
    seed();
    seed();

    assertDatabaseCount('businesses', 2);
    assertDatabaseCount('users', 8);

    foreach ([BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID => [3, 3], BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID => [1, 2]] as $businessId => [$members, $customers]) {
        $this->scopeQueriesTo($businessId);
        assertDatabaseCount('members', $members);
        assertDatabaseCount('customers', $customers);
    }
});

it('restores a missing demo row when it is seeded again', function () {
    seed();
    $this->scopeQueriesTo(BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID);
    DB::table('members')->whereIn('user_id', DB::table('users')->where('email', 'asesor@archivumparfums.test')->select('id'))->delete();
    $this->scopeQueriesTo(BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID);
    DB::table('customers')->where('business_id', BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID)->delete();

    seed();

    $this->scopeQueriesTo(BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID);
    expect(demoStaff('archivumparfums'))->toHaveKey('asesor@archivumparfums.test');
    $this->scopeQueriesTo(BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID);
    expect(demoCustomers('esencias-del-norte'))->toHaveCount(2);

    foreach ([BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID => [3, 3], BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID => [1, 2]] as $businessId => [$members, $customers]) {
        $this->scopeQueriesTo($businessId);
        assertDatabaseCount('members', $members);
        assertDatabaseCount('customers', $customers);
    }
});

it('refuses in spanish to seed without a demo password and leaves nothing behind', function () {
    config(['archivum.demo_password' => '']);

    expect(fn () => seed())
        ->toThrow(RuntimeException::class, 'Hay que definir ARCHIVUM_DEMO_PASSWORD en .env antes de sembrar las tiendas de demostración.');

    assertDatabaseCount('businesses', 0);
    assertDatabaseCount('users', 0);
});

it('refuses to seed demo accounts without a demo password even alone', function (string $seeder) {
    config(['archivum.demo_password' => null]);
    seed(BusinessesSeeder::class);

    expect(fn () => seed($seeder))->toThrow(RuntimeException::class, 'ARCHIVUM_DEMO_PASSWORD');

    assertDatabaseCount('users', 0);
})->with([StaffSeeder::class, CustomersSeeder::class]);

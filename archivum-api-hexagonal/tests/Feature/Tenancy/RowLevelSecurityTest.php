<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Members\Infrastructure\Persistence\Models\MemberModel;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

const POLICY_EXPRESSION = "(business_id = (NULLIF(current_setting('app.business_id'::text, true), ''::text))::uuid)";

/**
 * Tables protected by row-level security, each with the columns of a valid new row besides its business and its
 * account. Every issue that adds a table with business_id adds its row here, and the catalog test fails until it does.
 *
 * @return array<string, array{string, array<string, string>}>
 */
function protectedTables(): array
{
    return [
        'members' => ['members', ['role' => 'staff']],
        'customers' => ['customers', []],
    ];
}

/**
 * Eloquent models of the protected tables, as the repositories read them. Each one has a factory, which gives its
 * table one row in each of the two businesses before every test; a new model also joins the union of the return type.
 *
 * @return array<string, array{class-string<MemberModel|CustomerModel>}>
 */
function protectedModels(): array
{
    return [
        'members' => [MemberModel::class],
        'customers' => [CustomerModel::class],
    ];
}

/** Open an account of its own for a new row, so no unique key gets in the way of the policy. */
function accountForANewRow(): string
{
    return UserModel::factory()->create()->id;
}

/**
 * Run a write inside a savepoint and return the error PostgreSQL answered, or null if it accepted it.
 *
 * @param  Closure(): mixed  $write
 */
function writeError(Closure $write): ?QueryException
{
    try {
        DB::transaction($write);
    } catch (QueryException $exception) {
        return $exception;
    }

    return null;
}

/** Expect the write to be rejected by a row-level security policy. */
function expectRowLevelSecurityViolation(?QueryException $error): void
{
    expect($error?->getCode())->toBe('42501')
        ->and($error?->getMessage())->toContain('violates row-level security policy');
}

beforeEach(function () {
    TwoBusinesses::create();

    foreach ([TwoBusinesses::FIRST, TwoBusinesses::SECOND] as $slug) {
        foreach (protectedModels() as [$model]) {
            $model::factory()->create(['business_id' => TwoBusinesses::idOf($slug)]);
        }
    }
});

it('runs these tests with the application role, the one of every request', function () {
    expect(DB::scalar('select current_user'))->toBe(config('database.connections.pgsql.username'));
});

it('forces row-level security with its isolation policy on every table with business_id', function () {
    $tables = array_column(DB::select(
        "select table_name from information_schema.columns
          where table_schema = 'public' and column_name = 'business_id'
          order by table_name",
    ), 'table_name');
    $protected = array_keys(protectedTables());
    sort($protected);
    $modelled = array_keys(protectedModels());
    sort($modelled);
    // The catalog tables (#37) have no Eloquent model or seeded rows yet (#38, #39); CatalogSchemaTest exercises them.
    $catalog = ['perfume_notes', 'perfume_tags', 'perfume_variants', 'perfumes'];
    $withPolicy = [...$protected, ...$catalog];
    sort($withPolicy);

    expect($tables)->toBe($withPolicy)
        ->and($modelled)->toBe($protected);

    foreach ($withPolicy as $table) {
        $security = DB::selectOne(
            'select relrowsecurity, relforcerowsecurity from pg_class where oid = ?::regclass',
            [$table],
        );
        $isolation = DB::selectOne(
            "select cmd, roles::text as roles, qual, with_check from pg_policies
              where schemaname = 'public' and tablename = ? and policyname = ?",
            [$table, "{$table}_business_isolation"],
        );

        expect($security->relrowsecurity)->toBeTrue("Row-level security is not enabled on {$table}.")
            ->and($security->relforcerowsecurity)->toBeTrue("Row-level security is not forced on {$table}.")
            ->and($isolation)->toEqual((object) [
                'cmd' => 'ALL',
                'roles' => '{public}',
                'qual' => POLICY_EXPRESSION,
                'with_check' => POLICY_EXPRESSION,
            ], "The isolation policy of {$table} is missing or different.");
    }
});

it('adds no policy but the isolation one and the read of the definer functions', function () {
    $owner = config('database.connections.pgsql_admin.username');
    $others = DB::select(
        "select tablename, policyname, cmd, roles::text as roles, qual, with_check from pg_policies
          where schemaname = 'public' and policyname <> tablename || '_business_isolation'
          order by tablename",
    );

    expect($others)->toEqual([
        (object) ['tablename' => 'customers', 'policyname' => 'customers_definer_read', 'cmd' => 'SELECT', 'roles' => "{{$owner}}", 'qual' => 'true', 'with_check' => null],
        (object) ['tablename' => 'members', 'policyname' => 'members_definer_read', 'cmd' => 'SELECT', 'roles' => "{{$owner}}", 'qual' => 'true', 'with_check' => null],
    ]);
});

it('shows and changes no row when the transaction has no business', function (string $table) {
    $this->scopeQueriesTo(null);

    expect(DB::table($table)->count())->toBe(0)
        ->and(DB::table($table)->update(['updated_at' => now()]))->toBe(0)
        ->and(DB::table($table)->delete())->toBe(0);

    $this->scopeQueriesTo(TwoBusinesses::idOf(TwoBusinesses::FIRST));

    expect(DB::table($table)->count())->toBeGreaterThan(0);
})->with(protectedTables());

it('returns only the rows of the business of the transaction to an eloquent query without filter', function (string $model) {
    foreach ([TwoBusinesses::FIRST, TwoBusinesses::SECOND] as $slug) {
        $businessId = TwoBusinesses::idOf($slug);
        $this->scopeQueriesTo($businessId);

        expect($model::query()->distinct()->pluck('business_id')->all())->toBe([$businessId]);
    }
})->with(protectedModels());

it('refuses a new row for another business', function (string $table, array $row) {
    $this->scopeQueriesTo(TwoBusinesses::idOf(TwoBusinesses::FIRST));
    $userId = accountForANewRow();

    expectRowLevelSecurityViolation(writeError(fn () => DB::table($table)->insert([
        ...$row,
        'business_id' => TwoBusinesses::idOf(TwoBusinesses::SECOND),
        'user_id' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ])));
})->with(protectedTables());

it('refuses to move a row to another business and leaves it where it was', function (string $table) {
    $businessId = TwoBusinesses::idOf(TwoBusinesses::FIRST);
    $otherId = TwoBusinesses::idOf(TwoBusinesses::SECOND);
    $this->scopeQueriesTo($businessId);

    expectRowLevelSecurityViolation(writeError(fn () => DB::table($table)->update(['business_id' => $otherId])));
    expect(DB::table($table)->distinct()->pluck('business_id')->all())->toBe([$businessId]);
})->with(protectedTables());

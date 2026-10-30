<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Pest\Support\HigherOrderTapProxy;
use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Tests\Support\Businesses\StaffedBusiness;
use Tests\Support\Shared\ParallelProcesses;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot Laravel through Tests\TestCase. Unit and Architecture
| tests run without the framework.
|
*/

pest()->extend(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Acting as a person of a business
|--------------------------------------------------------------------------
|
| Each helper signs in one person of a business with its staff, a new one
| unless the test gives it, and returns that business. Each token carries
| the abilities of its role, as POST /api/v1/sesiones issues it (#102).
|
*/

/** Sign in as the owner of the business. */
function actingAsOwner(?StaffedBusiness $business = null): StaffedBusiness
{
    $business ??= StaffedBusiness::create();
    Sanctum::actingAs($business->owner, Ability::namesGrantedTo(AccountRole::Owner));

    return $business;
}

/** Sign in as the member of the staff of the business. */
function actingAsStaff(?StaffedBusiness $business = null): StaffedBusiness
{
    $business ??= StaffedBusiness::create();
    Sanctum::actingAs($business->staff, Ability::namesGrantedTo(AccountRole::Staff));

    return $business;
}

/** Sign in as the customer of the business. */
function actingAsCustomer(?StaffedBusiness $business = null): StaffedBusiness
{
    $business ??= StaffedBusiness::create();
    Sanctum::actingAs($business->customer, Ability::namesGrantedTo(AccountRole::Customer));

    return $business;
}

/** Sign in as a role named as GET /api/v1/cuenta names it; the visitor signs in nobody. */
function actingAsRole(string $role, ?StaffedBusiness $business = null): StaffedBusiness
{
    if ($role === 'visitante') {
        Auth::forgetGuards();

        return $business ?? StaffedBusiness::create();
    }

    return match ($role) {
        'dueno' => actingAsOwner($business),
        'colaborador' => actingAsStaff($business),
        'cliente' => actingAsCustomer($business),
        default => throw new InvalidArgumentException("Unknown role [{$role}]."),
    };
}

/*
|--------------------------------------------------------------------------
| Running operations in parallel
|--------------------------------------------------------------------------
|
| The tests of the critical paths make two requests compete for the same
| row. Rows written inside the transaction of the test are invisible to
| other connections, so the test commits the rows the operations share
| with DB::connection('pgsql_admin') and lists every table written that
| way. An operation cannot use $this, receives only plain values through
| use() and starts on a line of its own, because the serializer cannot
| tell apart two closures that start on the same line.
|
*/

/**
 * Run each operation in its own process, with its own connection and outside the transaction of the test,
 * all of them at the same instant, and return their results in order. The tables are emptied when the test ends.
 *
 * @param  list<string>  $tables  every table the test and the operations write outside the transaction
 * @return list<mixed>
 */
function inParallel(array $tables, Closure ...$operations): array
{
    if ($tables !== []) {
        $test = test();
        assert($test instanceof HigherOrderTapProxy);

        // Laravel calls this after the transaction of the test rolls back, so no lock is left on the tables.
        // @phpstan-ignore method.notFound (the proxy forwards the call to the running test case)
        $test->beforeApplicationDestroyed(fn () => ParallelProcesses::emptyTables(array_values(array_unique($tables))));
    }

    return ParallelProcesses::run(array_values($operations));
}

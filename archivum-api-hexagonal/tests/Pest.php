<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Tests\Support\Businesses\StaffedBusiness;
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
| unless the test gives it, and returns that business. The tokens of the
| API carry every ability, so these do too.
|
*/

/** Sign in as the owner of the business. */
function actingAsOwner(?StaffedBusiness $business = null): StaffedBusiness
{
    $business ??= StaffedBusiness::create();
    Sanctum::actingAs($business->owner, ['*']);

    return $business;
}

/** Sign in as the member of the staff of the business. */
function actingAsStaff(?StaffedBusiness $business = null): StaffedBusiness
{
    $business ??= StaffedBusiness::create();
    Sanctum::actingAs($business->staff, ['*']);

    return $business;
}

/** Sign in as the customer of the business. */
function actingAsCustomer(?StaffedBusiness $business = null): StaffedBusiness
{
    $business ??= StaffedBusiness::create();
    Sanctum::actingAs($business->customer, ['*']);

    return $business;
}

/** Sign in as a role named as GET /api/v1/cuenta names it; the visitor signs in nobody. */
function actingAsRole(string $role, ?StaffedBusiness $business = null): StaffedBusiness
{
    return match ($role) {
        'dueno' => actingAsOwner($business),
        'colaborador' => actingAsStaff($business),
        'cliente' => actingAsCustomer($business),
        'visitante' => $business ?? StaffedBusiness::create(),
        default => throw new InvalidArgumentException("Unknown role [{$role}]."),
    };
}

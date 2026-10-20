<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToBusiness;

pest()->use(RefreshDatabaseAsOwner::class, ScopesQueriesToBusiness::class);

const UNKNOWN_ACCOUNT = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d98';

/**
 * Identifier of the customer record of an account in one of the two businesses, read with that business pinned
 * and leaving the transaction without a business again.
 *
 * @param  Closure(?string): void  $scopeQueriesTo
 */
function customerIdOf(Closure $scopeQueriesTo, string $email, string $slug): string
{
    $businessId = TwoBusinesses::idOf($slug);
    $scopeQueriesTo($businessId);

    $customerId = DB::table('customers')
        ->where('business_id', $businessId)
        ->where('user_id', TwoBusinesses::userIdOf($email))
        ->value('id');
    $scopeQueriesTo(null);

    return (string) $customerId;
}

/** Business that fn_customer_resource_business answers for a customer record and an account. */
function businessOfCustomerRecord(string $customerId, string $userId): mixed
{
    return DB::scalar('select fn_customer_resource_business(?, ?, ?)', ['customer', $customerId, $userId]);
}

beforeEach(function () {
    TwoBusinesses::create();
    $this->scopeQueriesTo(null);
});

it('answers only the business and the role of a person of the staff', function (string $email, string $slug, string $role) {
    $rows = DB::select('select * from fn_member_business(?)', [TwoBusinesses::userIdOf($email)]);

    expect(array_map(fn (object $row): array => (array) $row, $rows))
        ->toBe([['business_id' => TwoBusinesses::idOf($slug), 'role' => $role]]);
})->with([
    'owner of the first business' => [TwoBusinesses::OWNERS[TwoBusinesses::FIRST], TwoBusinesses::FIRST, 'owner'],
    'advisor of the first business' => [TwoBusinesses::ADVISOR, TwoBusinesses::FIRST, 'staff'],
    'owner of the second business' => [TwoBusinesses::OWNERS[TwoBusinesses::SECOND], TwoBusinesses::SECOND, 'owner'],
]);

it('answers no business of the staff for a customer or an unknown account', function (string $userId) {
    expect(DB::select('select * from fn_member_business(?)', [$userId]))->toBe([]);
})->with([
    'customer' => fn () => TwoBusinesses::userIdOf(TwoBusinesses::CUSTOMER),
    'unknown account' => UNKNOWN_ACCOUNT,
]);

it('answers only the businesses where the account is a customer', function (string $email, array $slugs) {
    $businesses = array_column(
        DB::select('select fn_customer_businesses(?) as business_id', [TwoBusinesses::userIdOf($email)]),
        'business_id',
    );
    $expected = array_map(TwoBusinesses::idOf(...), $slugs);
    sort($expected);

    expect($businesses)->toBe($expected);
})->with([
    'customer of both businesses' => [TwoBusinesses::CUSTOMER_OF_BOTH, [TwoBusinesses::FIRST, TwoBusinesses::SECOND]],
    'customer of the first business' => [TwoBusinesses::CUSTOMER, [TwoBusinesses::FIRST]],
    'owner, who is no customer' => [TwoBusinesses::OWNERS[TwoBusinesses::FIRST], []],
]);

it('answers the business of a resource of the customer in each business', function (string $slug) {
    $customerId = customerIdOf($this->scopeQueriesTo(...), TwoBusinesses::CUSTOMER_OF_BOTH, $slug);

    expect(businessOfCustomerRecord($customerId, TwoBusinesses::userIdOf(TwoBusinesses::CUSTOMER_OF_BOTH)))
        ->toBe(TwoBusinesses::idOf($slug));
})->with([TwoBusinesses::FIRST, TwoBusinesses::SECOND]);

it('answers no business for a resource of another customer or one that does not exist', function () {
    $foreign = customerIdOf($this->scopeQueriesTo(...), TwoBusinesses::CUSTOMER_OF_BOTH, TwoBusinesses::FIRST);
    $userId = TwoBusinesses::userIdOf(TwoBusinesses::CUSTOMER);

    expect(businessOfCustomerRecord($foreign, $userId))->toBeNull()
        ->and(businessOfCustomerRecord(UNKNOWN_ACCOUNT, $userId))->toBeNull();
});

it('refuses a type of resource outside its closed list', function () {
    $error = null;

    try {
        DB::transaction(fn () => DB::scalar(
            'select fn_customer_resource_business(?, ?, ?)',
            ['invoice', UNKNOWN_ACCOUNT, UNKNOWN_ACCOUNT],
        ));
    } catch (QueryException $exception) {
        $error = $exception;
    }

    expect($error?->getCode())->toBe('22023')
        ->and($error?->getMessage())->toContain('Unknown resource type of a customer: invoice');
});

it('runs the three functions as the schema owner, with an empty search_path, and lets only the application role call them', function (string $function) {
    $definition = DB::selectOne(
        "select prosecdef, proconfig, pg_get_userbyid(proowner) as owner,
                array(select coalesce(nullif(grantee::regrole::text, '-'), 'PUBLIC')
                        from aclexplode(proacl) where privilege_type = 'EXECUTE' order by 1) as callers
           from pg_proc where oid = ?::regprocedure",
        [$function],
    );
    $owner = config('database.connections.pgsql_admin.username');
    $application = config('database.connections.pgsql.username');
    $callers = [$owner, $application];
    sort($callers);

    expect($definition->prosecdef)->toBeTrue()
        ->and($definition->proconfig)->toBe('{"search_path=\"\""}')
        ->and($definition->owner)->toBe($owner)
        ->and($definition->callers)->toBe('{'.implode(',', $callers).'}');
})->with([
    'fn_member_business(uuid)',
    'fn_customer_businesses(uuid)',
    'fn_customer_resource_business(text, uuid, uuid)',
]);

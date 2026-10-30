<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Businesses\Members\Application\DTOs\UpdateMemberCommand;
use Src\Businesses\Members\Application\UseCases\UpdateMember;
use Src\Businesses\Members\Domain\Exceptions\LastActiveOwnerException;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

/**
 * Two owners who deactivate each other at the same instant: the lock on the active owners lets only one through,
 * so the business never ends without an active owner. It needs two real connections, so it commits its rows.
 */
it('keeps one active owner when two owners deactivate each other at once', function () {
    $admin = DB::connection('pgsql_admin');
    $now = now();
    $business = (string) Str::uuid7();
    $admin->table('businesses')->insert(['id' => $business, 'name' => 'Dos dueños', 'slug' => 'dos-duenos-'.$business, 'created_at' => $now, 'updated_at' => $now]);

    $ids = $admin->transaction(function () use ($admin, $business, $now): array {
        $admin->statement("SELECT set_config('app.business_id', ?, true)", [$business]);
        $ids = [];

        foreach (['uno', 'dos'] as $person) {
            $user = (string) Str::uuid7();
            $member = (string) Str::uuid7();
            $admin->table('users')->insert(['id' => $user, 'name' => "Dueño {$person}", 'email' => "{$person}@duenos.test", 'password' => 'x', 'created_at' => $now, 'updated_at' => $now]);
            $admin->table('members')->insert(['id' => $member, 'business_id' => $business, 'user_id' => $user, 'role' => 'owner', 'created_at' => $now, 'updated_at' => $now]);
            $ids[] = $member;
        }

        return $ids;
    });

    [$first, $second] = $ids;

    $results = inParallel(
        ['members', 'users', 'businesses'],
        static function () use ($business, $first): string {
            try {
                app(BusinessTransaction::class)->run($business, fn () => app(UpdateMember::class)->execute(
                    new UpdateMemberCommand($business, $first, null, null, null, false),
                ));

                return 'updated';
            } catch (LastActiveOwnerException) {
                return 'refused';
            }
        },
        static function () use ($business, $second): string {
            try {
                app(BusinessTransaction::class)->run($business, fn () => app(UpdateMember::class)->execute(
                    new UpdateMemberCommand($business, $second, null, null, null, false),
                ));

                return 'updated';
            } catch (LastActiveOwnerException) {
                return 'refused';
            }
        },
    );

    sort($results);

    expect($results)->toBe(['refused', 'updated'])
        ->and($admin->transaction(function () use ($admin, $business): int {
            $admin->statement("SELECT set_config('app.business_id', ?, true)", [$business]);

            return $admin->table('members')->where('business_id', $business)->where('is_active', true)->count();
        }))->toBe(1);
});

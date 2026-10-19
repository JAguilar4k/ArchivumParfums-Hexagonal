<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Migrations that had already shipped before accounts adopted UUID identifiers. */
const ORIGINAL_ACCOUNT_MIGRATIONS = [
    'database/migrations/0000_00_00_000000_grant_privileges_to_the_application_role.php',
    'database/migrations/0001_01_01_000000_create_users_table.php',
    'database/migrations/0001_01_01_000001_create_cache_table.php',
    'database/migrations/0001_01_01_000002_create_jobs_table.php',
];

afterEach(function () {
    // These tests change the migration history without RefreshDatabase's outer transaction.
    RefreshDatabaseState::$migrated = false;
});

it('upgrades the original accounts and restores their data and numeric references on rollback', function () {
    expect(Artisan::call('migrate:fresh', [
        '--database' => 'pgsql_admin',
        '--path' => ORIGINAL_ACCOUNT_MIGRATIONS,
        '--force' => true,
    ]))->toBe(0);

    $userId = DB::table('users')->insertGetId([
        'name' => str_repeat('A', 150),
        'email' => 'legacy@archivum.test',
        'password' => 'original-password-hash',
        'email_verified_at' => '2026-09-15 10:00:00',
        'remember_token' => 'original-remember-token',
        'created_at' => '2026-09-14 10:00:00',
        'updated_at' => '2026-09-16 10:00:00',
    ]);
    $originalUser = DB::table('users')->where('id', $userId)->sole();
    DB::table('password_reset_tokens')->insert([
        'email' => 'legacy@archivum.test',
        'token' => 'original-reset-token',
        'created_at' => '2026-09-17 10:00:00',
    ]);

    foreach (['account-session' => $userId, 'orphan-session' => 9999, 'visitor-session' => null] as $id => $account) {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $account,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Fixture browser',
            'payload' => 'original-session-payload',
            'last_activity' => 1790000000,
        ]);
    }

    $originalSessions = DB::table('sessions')->orderBy('id')->get()->all();

    expect(Artisan::call('migrate', ['--database' => 'pgsql_admin', '--force' => true]))->toBe(0);

    $upgraded = DB::table('users')->where('email', 'legacy@archivum.test')->sole();

    expect(Str::isUuid($upgraded->id, 7))->toBeTrue();
    expect(DB::table('users')->count())->toBe(1);
    expect($upgraded->legacy_id)->toBe($userId);
    expect($upgraded->name)->toBe(str_repeat('A', 150));
    expect($upgraded->password)->toBe('original-password-hash');
    expect($upgraded->remember_token)->toBe('original-remember-token');
    expect(DB::table('sessions')->where('id', 'account-session')->value('user_id'))->toBe($upgraded->id);
    expect(DB::table('sessions')->where('id', 'orphan-session')->value('legacy_user_id'))->toBe(9999);

    $newId = DB::table('users')->insertGetId([
        'name' => 'Nueva cuenta',
        'email' => 'new@archivum.test',
        'password' => 'new-password-hash',
    ]);
    DB::table('sessions')->insert([
        'id' => 'new-session',
        'user_id' => $newId,
        'payload' => 'new-session-payload',
        'last_activity' => 1790000001,
    ]);

    expect(Str::isUuid($newId, 7))->toBeTrue();
    expect(Artisan::call('migrate:rollback', ['--database' => 'pgsql_admin', '--force' => true]))->toBe(0);
    expect(DB::table('users')->where('email', 'legacy@archivum.test')->sole())->toEqual($originalUser);
    expect(DB::table('users')->where('email', 'new@archivum.test')->value('id'))->toBe(2);
    expect(DB::table('sessions')->where('id', 'new-session')->value('user_id'))->toBe(2);
    expect(DB::table('sessions')->where('id', '!=', 'new-session')->orderBy('id')->get()->all())->toEqual($originalSessions);
    expect(DB::table('password_reset_tokens')->where('email', 'legacy@archivum.test')->value('token'))->toBe('original-reset-token');

    expect(Artisan::call('migrate', ['--database' => 'pgsql_admin', '--force' => true]))->toBe(0);
    expect(DB::table('users')->count())->toBe(2);
    expect(Str::isUuid(DB::table('users')->where('email', 'legacy@archivum.test')->value('id'), 7))->toBeTrue();
});

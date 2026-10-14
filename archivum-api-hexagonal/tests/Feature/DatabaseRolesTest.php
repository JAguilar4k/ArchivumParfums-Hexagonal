<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

it('serves requests with the application role', function () {
    expect(DB::scalar('select current_user'))->toBe('archivum_app')
        ->and(DB::connection('pgsql_admin')->scalar('select current_user'))->toBe('archivum_owner');
});

it('gives the application role no elevated attributes', function () {
    $role = (array) DB::selectOne(
        'select rolsuper, rolbypassrls, rolcreatedb, rolcreaterole from pg_roles where rolname = current_user',
    );

    expect($role)->toBe([
        'rolsuper' => false,
        'rolbypassrls' => false,
        'rolcreatedb' => false,
        'rolcreaterole' => false,
    ]);
});

it('lets the application role own no table or sequence', function () {
    $owned = DB::scalar(
        "select count(*) from pg_class where relowner = (select oid from pg_roles where rolname = current_user) and relkind in ('r', 'p', 'S', 'v', 'm')",
    );

    expect($owned)->toBe(0);
});

it('lets the application role read and write rows', function () {
    DB::table('cache')->insert(['key' => 'role-check', 'value' => 'ok', 'expiration' => 0]);
    DB::table('cache')->where('key', 'role-check')->update(['value' => 'updated']);

    expect(DB::table('cache')->where('key', 'role-check')->value('value'))->toBe('updated');

    DB::table('cache')->where('key', 'role-check')->delete();

    expect(DB::table('cache')->where('key', 'role-check')->exists())->toBeFalse();
});

it('stops the application role from changing the schema', function (string $statement) {
    expect(fn () => DB::statement($statement))->toThrow(QueryException::class, 'Insufficient privilege');
})->with([
    'create table' => 'create table forbidden (id integer)',
    'alter table' => 'alter table cache add column forbidden integer',
    'drop table' => 'drop table cache',
]);

it('grants the application role every table created by the migrations', function () {
    $missing = DB::select(
        "select c.relname from pg_class c
           join pg_namespace n on n.oid = c.relnamespace
          where n.nspname = 'public'
            and c.relkind in ('r', 'p')
            and c.relname <> 'migrations'
            and not has_table_privilege(current_user, c.oid, 'select, insert, update, delete')",
    );

    expect(DB::scalar("select count(*) from pg_tables where schemaname = 'public'"))->toBeGreaterThan(1)
        ->and($missing)->toBe([]);
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

/**
 * Tables, views, types, functions and default privileges the migrations leave in the public schema.
 *
 * Objects that belong to an extension are left out: no rollback drops an extension such as btree_gist.
 *
 * @return array<string, mixed>
 */
function publicSchema(): array
{
    $schema = Schema::connection('pgsql_admin');
    $tables = [];

    foreach ($schema->getTableListing('public', schemaQualified: false) as $table) {
        $tables[$table] = [
            'columns' => $schema->getColumns($table),
            'indexes' => $schema->getIndexes($table),
            'foreign_keys' => $schema->getForeignKeys($table),
        ];
    }

    return [
        'tables' => $tables,
        'views' => $schema->getViews('public'),
        'types' => $schema->getTypes('public'),
        'routines' => DB::connection('pgsql_admin')->select(
            "select p.proname, pg_get_function_identity_arguments(p.oid) as arguments from pg_proc p join pg_namespace n on n.oid = p.pronamespace where n.nspname = 'public' and not exists (select 1 from pg_depend d where d.objid = p.oid and d.deptype = 'e') order by 1, 2",
        ),
        'default_privileges' => DB::connection('pgsql_admin')->select(
            "select d.defaclobjtype, d.defaclacl::text from pg_default_acl d join pg_namespace n on n.oid = d.defaclnamespace where n.nspname = 'public' order by 1",
        ),
    ];
}

/**
 * Migrations that have run, the most recent first.
 *
 * @return list<string>
 */
function ranMigrations(): array
{
    /** @var list<string> */
    return DB::connection('pgsql_admin')->table('migrations')
        ->orderByDesc('batch')
        ->orderByDesc('id')
        ->pluck('migration')
        ->all();
}

it('rolls back every migration one by one and applies them again', function () {
    $migrated = publicSchema();
    $pending = ranMigrations();

    expect($pending)->not->toBeEmpty();

    foreach ($pending as $index => $migration) {
        expect(Artisan::call('migrate:rollback', ['--database' => 'pgsql_admin', '--step' => 1, '--force' => true]))
            ->toBe(0, "Rolling back {$migration} failed.");

        expect(ranMigrations())->toBe(array_slice($pending, $index + 1), "{$migration} did not roll back.");
    }

    $leftovers = publicSchema();

    expect(array_keys($leftovers['tables']))->toBe(['migrations'], 'A migration left a table behind.')
        ->and($leftovers['views'])->toBe([], 'A migration left a view behind.')
        ->and($leftovers['types'])->toBe([], 'A migration left a type behind.')
        ->and($leftovers['routines'])->toBe([], 'A migration left a function or procedure behind.')
        ->and($leftovers['default_privileges'])->toBe([], 'A migration left default privileges behind.');

    expect(Artisan::call('migrate', ['--database' => 'pgsql_admin', '--force' => true]))->toBe(0)
        ->and(publicSchema())->toEqual($migrated);
});

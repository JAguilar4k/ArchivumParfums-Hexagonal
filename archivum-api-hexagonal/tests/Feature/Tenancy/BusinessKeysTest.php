<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabaseAsOwner;

pest()->use(RefreshDatabaseAsOwner::class);

/**
 * Unique and foreign keys of a table with business_id that leave business_id out. PostgreSQL checks keys without
 * the row policies, so each exception is deliberate and docs/data-model.md (#35) justifies it; the list grows with
 * the issues that need one, like decant_movements.user_id, payments.recorded_by or catalog_imports.created_by.
 */
const KEYS_WITHOUT_BUSINESS = [
    // Accounts have no business: an account is a customer of several businesses.
    'customers_user_id_foreign',
    'members_user_id_foreign',
    // A person of the staff belongs to one business only.
    'members_user_id_unique',
];

it('includes business_id in every unique, exclusion and foreign key of a table of a business, save the listed exceptions', function () {
    $keys = array_column(DB::select(
        "with business_tables as (
             select a.attrelid as table_oid, a.attnum as business_column
               from pg_attribute a
               join pg_class c on c.oid = a.attrelid
               join pg_namespace n on n.oid = c.relnamespace
              where n.nspname = 'public' and c.relkind in ('r', 'p') and a.attname = 'business_id' and not a.attisdropped
         )
         select con.conname as name
           from pg_constraint con
           join business_tables t on t.table_oid = con.conrelid
          where con.contype in ('u', 'f', 'x') and not t.business_column = any (con.conkey)
         union
         select idx.relname
           from pg_index i
           join pg_class idx on idx.oid = i.indexrelid
           join business_tables t on t.table_oid = i.indrelid
          where i.indisunique and not i.indisprimary and not t.business_column = any (i.indkey::smallint[])
            and not exists (select 1 from pg_constraint con where con.conindid = i.indexrelid)
          order by name",
    ), 'name');
    $exceptions = KEYS_WITHOUT_BUSINESS;
    sort($exceptions);

    expect($keys)->toBe($exceptions);
});

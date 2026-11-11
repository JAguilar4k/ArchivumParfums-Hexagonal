<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * PostgreSQL 18 already ships uuidv7() in pg_catalog, and every table of the project defaults its key to it.
     * This migration only defines a fallback of its own when the server of an older installation lacks the native
     * function, and never touches the object of the server.
     */
    public function up(): void
    {
        if (DB::scalar("SELECT to_regprocedure('pg_catalog.uuidv7()') IS NOT NULL")) {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.uuidv7() RETURNS uuid AS $$
            DECLARE
                unix_ts_ms bytea;
                uuid_bytes bytea;
            BEGIN
                unix_ts_ms := substring(int8send((extract(epoch from clock_timestamp()) * 1000)::bigint) from 3);
                uuid_bytes := unix_ts_ms || gen_random_bytes(10);
                uuid_bytes := overlay(uuid_bytes placing set_bit(get_bit(uuid_bytes, 48), 48, 0) from 7 for 1);
                uuid_bytes := overlay(uuid_bytes placing set_bit(get_bit(uuid_bytes, 49), 49, 1) from 7 for 1);
                uuid_bytes := overlay(uuid_bytes placing set_bit(get_bit(uuid_bytes, 50), 50, 1) from 7 for 1);
                uuid_bytes := overlay(uuid_bytes placing set_bit(get_bit(uuid_bytes, 51), 51, 1) from 7 for 1);
                uuid_bytes := overlay(uuid_bytes placing set_bit(get_bit(uuid_bytes, 64), 64, 1) from 9 for 1);
                uuid_bytes := overlay(uuid_bytes placing set_bit(get_bit(uuid_bytes, 65), 65, 0) from 9 for 1);
                RETURN encode(uuid_bytes, 'hex')::uuid;
            END;
            $$ LANGUAGE plpgsql;
            SQL);
    }

    /**
     * Drop only the function this migration could have created, by its schema and signature.
     * The native uuidv7() belongs to pg_catalog and to the superuser, so it is never dropped.
     */
    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS public.uuidv7()');
    }
};

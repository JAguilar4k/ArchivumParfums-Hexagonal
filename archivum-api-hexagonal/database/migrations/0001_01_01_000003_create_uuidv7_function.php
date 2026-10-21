<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE FUNCTION uuidv7() RETURNS uuid AS $$
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
        ");
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS uuidv7();');
    }
};

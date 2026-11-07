<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Deactivate members without deleting them. A deactivated person resolves no business: fn_member_business stops
     * finding them, and fn_member_deactivated lets the sign-in refuse them before any business is pinned. It returns
     * only a boolean, never a row, and only the application role may call it.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE members ADD COLUMN is_active boolean NOT NULL DEFAULT true');

        // CREATE OR REPLACE keeps the owner and the grants of the function.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_member_business(p_user_id uuid)
                RETURNS TABLE (business_id uuid, role text)
                LANGUAGE sql
                STABLE
                SECURITY DEFINER
                SET search_path = ''
            AS $$
                SELECT m.business_id, m.role::text
                FROM public.members m
                WHERE m.user_id = p_user_id AND m.is_active
            $$;
            SQL);

        // migrate:fresh drops the tables but not the functions.
        DB::statement('DROP FUNCTION IF EXISTS fn_member_deactivated(text)');

        DB::unprepared(<<<'SQL'
            CREATE FUNCTION fn_member_deactivated(p_email text)
                RETURNS boolean
                LANGUAGE sql
                STABLE
                SECURITY DEFINER
                SET search_path = ''
            AS $$
                SELECT EXISTS (
                    SELECT 1
                    FROM public.members m
                    JOIN public.users u ON u.id = m.user_id
                    WHERE u.email = p_email AND NOT m.is_active
                )
            $$;
            SQL);

        $name = config('database.connections.pgsql.username');
        $role = '"'.str_replace('"', '""', is_string($name) ? $name : '').'"';

        DB::statement('REVOKE ALL ON FUNCTION fn_member_deactivated(text) FROM PUBLIC');
        DB::statement("GRANT EXECUTE ON FUNCTION fn_member_deactivated(text) TO {$role}");
    }

    /** Remove the function and the column, and let fn_member_business find every member again. */
    public function down(): void
    {
        DB::statement('DROP FUNCTION fn_member_deactivated(text)');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_member_business(p_user_id uuid)
                RETURNS TABLE (business_id uuid, role text)
                LANGUAGE sql
                STABLE
                SECURITY DEFINER
                SET search_path = ''
            AS $$
                SELECT m.business_id, m.role::text
                FROM public.members m
                WHERE m.user_id = p_user_id
            $$;
            SQL);

        DB::statement('ALTER TABLE members DROP COLUMN is_active');
    }
};

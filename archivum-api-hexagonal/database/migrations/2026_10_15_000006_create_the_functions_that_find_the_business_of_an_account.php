<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Functions that answer which business an account or a resource of a customer belongs to, before any business
     * is pinned in the transaction. Each signature is listed so up() and down() treat the same functions.
     *
     * @var list<string>
     */
    private const array FUNCTIONS = [
        'fn_member_business(uuid)',
        'fn_customer_businesses(uuid)',
        'fn_customer_resource_business(text, uuid, uuid)',
    ];

    /**
     * Create the three functions as SECURITY DEFINER with an empty search_path: they run as the schema owner,
     * so they reach members and customers without a business, and they return only business identifiers and the
     * role, never whole rows. Only the application role may call them. migrate:fresh drops the tables but not the
     * functions, so each one is dropped before it is created.
     */
    public function up(): void
    {
        foreach (self::FUNCTIONS as $function) {
            DB::statement("DROP FUNCTION IF EXISTS {$function}");
        }

        DB::unprepared(<<<'SQL'
            CREATE FUNCTION fn_member_business(p_user_id uuid)
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

            CREATE FUNCTION fn_customer_businesses(p_user_id uuid)
                RETURNS SETOF uuid
                LANGUAGE sql
                STABLE
                SECURITY DEFINER
                SET search_path = ''
            AS $$
                SELECT c.business_id
                FROM public.customers c
                WHERE c.user_id = p_user_id
                ORDER BY c.business_id
            $$;

            CREATE FUNCTION fn_customer_resource_business(p_type text, p_id uuid, p_user_id uuid)
                RETURNS uuid
                LANGUAGE plpgsql
                STABLE
                SECURITY DEFINER
                SET search_path = ''
            AS $$
            BEGIN
                -- Closed list: each schema with resources of a customer adds its type here (appointment with #59, order with #78).
                CASE p_type
                    WHEN 'customer' THEN
                        RETURN (
                            SELECT c.business_id
                            FROM public.customers c
                            WHERE c.id = p_id AND c.user_id = p_user_id
                        );
                    ELSE
                        RAISE EXCEPTION 'Unknown resource type of a customer: %', p_type
                            USING ERRCODE = 'invalid_parameter_value';
                END CASE;
            END;
            $$;
            SQL);

        $role = $this->applicationRole();

        foreach (self::FUNCTIONS as $function) {
            DB::statement("REVOKE ALL ON FUNCTION {$function} FROM PUBLIC");
            DB::statement("GRANT EXECUTE ON FUNCTION {$function} TO {$role}");
        }
    }

    /** Drop the three functions. */
    public function down(): void
    {
        foreach (array_reverse(self::FUNCTIONS) as $function) {
            DB::statement("DROP FUNCTION {$function}");
        }
    }

    /** Quoted name of the role the application connects with. */
    private function applicationRole(): string
    {
        $name = config('database.connections.pgsql.username');

        return '"'.str_replace('"', '""', is_string($name) ? $name : '').'"';
    }
};

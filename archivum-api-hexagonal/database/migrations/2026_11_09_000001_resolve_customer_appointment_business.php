<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Teach fn_customer_resource_business to resolve the store of a customer's appointment (#64). */
    public function up(): void
    {
        // The function runs as the schema owner before any business is pinned, and the table forces row-level
        // security on every role, so it needs a policy that lets the definer read the appointment it is asked for.
        $owner = config('database.connections.pgsql_admin.username');
        $quotedOwner = '"'.str_replace('"', '""', is_string($owner) ? $owner : '').'"';

        DB::statement('DROP POLICY IF EXISTS appointments_definer_read ON appointments');
        DB::statement("CREATE POLICY appointments_definer_read ON appointments FOR SELECT TO {$quotedOwner} USING (true)");

        $this->resourceFunction(true);
    }

    /** Leave the function and the table as the scheduling schema left them. */
    public function down(): void
    {
        $this->resourceFunction(false);

        DB::statement('DROP POLICY IF EXISTS appointments_definer_read ON appointments');
    }

    /**
     * Create the function again with, or without, the branch of the appointments. The signature and the grants stay,
     * so only the body of the function changes.
     */
    private function resourceFunction(bool $includeAppointments): void
    {
        $appointmentBranch = $includeAppointments ? <<<'SQL'
            WHEN 'appointment' THEN
                RETURN (SELECT a.business_id FROM public.appointments a
                    JOIN public.customers c ON c.id = a.customer_id AND c.business_id = a.business_id
                    WHERE a.id = p_id AND c.user_id = p_user_id);
            SQL : '';

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION fn_customer_resource_business(p_type text, p_id uuid, p_user_id uuid)
                RETURNS uuid LANGUAGE plpgsql STABLE SECURITY DEFINER SET search_path = ''
            AS \$\$
            BEGIN
                CASE p_type
                    WHEN 'customer' THEN
                        RETURN (SELECT c.business_id FROM public.customers c WHERE c.id = p_id AND c.user_id = p_user_id);
                    WHEN 'order' THEN
                        RETURN (SELECT o.business_id FROM public.orders o JOIN public.customers c
                            ON c.id = o.customer_id AND c.business_id = o.business_id
                            WHERE o.id = p_id AND c.user_id = p_user_id);
                    WHEN 'payment' THEN
                        RETURN (SELECT p.business_id FROM public.payments p
                            JOIN public.orders o ON o.id = p.order_id AND o.business_id = p.business_id
                            JOIN public.customers c ON c.id = o.customer_id AND c.business_id = o.business_id
                            WHERE p.id = p_id AND c.user_id = p_user_id);
                    {$appointmentBranch}
                    ELSE RAISE EXCEPTION 'Unknown resource type of a customer: %', p_type USING ERRCODE = 'invalid_parameter_value';
                END CASE;
            END;
            \$\$;
            SQL);
    }
};

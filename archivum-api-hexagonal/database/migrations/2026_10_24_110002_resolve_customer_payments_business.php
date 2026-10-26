<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $owner = config('database.connections.pgsql_admin.username');
        $quotedOwner = '"'.str_replace('"', '""', is_string($owner) ? $owner : '').'"';
        DB::statement("CREATE POLICY payments_definer_read ON payments FOR SELECT TO {$quotedOwner} USING (true)");
        $this->resourceFunction(true);
    }

    public function down(): void
    {
        $this->resourceFunction(false);
        DB::statement('DROP POLICY payments_definer_read ON payments');
    }

    private function resourceFunction(bool $includePayments): void
    {
        $paymentBranch = $includePayments ? <<<'SQL'
            WHEN 'payment' THEN
                RETURN (SELECT p.business_id FROM public.payments p
                    JOIN public.orders o ON o.id = p.order_id AND o.business_id = p.business_id
                    JOIN public.customers c ON c.id = o.customer_id AND c.business_id = o.business_id
                    WHERE p.id = p_id AND c.user_id = p_user_id);
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
                    {$paymentBranch}
                    ELSE RAISE EXCEPTION 'Unknown resource type of a customer: %', p_type USING ERRCODE = 'invalid_parameter_value';
                END CASE;
            END;
            \$\$;
            SQL);
    }
};

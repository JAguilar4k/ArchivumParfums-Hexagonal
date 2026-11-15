<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Signature of the function, so up() and down() treat the same one. */
    private const string FUNCTION = 'fn_payment_business(text, text)';

    /**
     * Answer which business a reference of the provider belongs to, before any business is pinned: a verified notice
     * that does not bring back the business Archivum sent with the charge only names the provider's charge, checkout
     * or capture. Like the functions of the accounts, it is SECURITY DEFINER with an empty search_path: it runs as
     * the schema owner, reads payments through the payments_definer_read policy and returns only the business
     * identifier, never the payment. Only the application role may call it. migrate:fresh drops the tables but not
     * the functions, so the function is dropped before it is created.
     */
    public function up(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS '.self::FUNCTION);

        DB::unprepared(<<<'SQL'
            CREATE FUNCTION fn_payment_business(p_gateway text, p_reference text)
                RETURNS uuid
                LANGUAGE sql
                STABLE
                SECURITY DEFINER
                SET search_path = ''
            AS $$
                SELECT p.business_id
                FROM public.payments p
                WHERE p.gateway = p_gateway
                    AND p_reference IN (p.provider_reference, p.checkout_reference, p.capture_reference)
                ORDER BY p.id
                LIMIT 1
            $$;
            SQL);

        DB::statement('REVOKE ALL ON FUNCTION '.self::FUNCTION.' FROM PUBLIC');
        DB::statement('GRANT EXECUTE ON FUNCTION '.self::FUNCTION.' TO '.$this->applicationRole());
    }

    /** Drop the function. */
    public function down(): void
    {
        DB::statement('DROP FUNCTION '.self::FUNCTION);
    }

    /** Quoted name of the role the application connects with. */
    private function applicationRole(): string
    {
        $name = config('database.connections.pgsql.username');

        return '"'.str_replace('"', '""', is_string($name) ? $name : '').'"';
    }
};

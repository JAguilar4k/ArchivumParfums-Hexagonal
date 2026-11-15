<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A refund keeps the amount it asks of the provider, in the unit the provider charged the payment (whole colones
     * with ONVO, cents of a dollar with PayPal), fixed when the request leaves (#97): a refund that left always says
     * how much it asked. Only ONVO refunds left before this change, and each one asked its own colones. Row security is
     * lifted for the owner of the tables only while those are filled in, inside the transaction of this migration.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE refunds ADD COLUMN charged_amount integer;
            ALTER TABLE refunds NO FORCE ROW LEVEL SECURITY;
            ALTER TABLE payments NO FORCE ROW LEVEL SECURITY;
            UPDATE refunds r SET charged_amount = r.amount
              FROM payments p
             WHERE p.id = r.payment_id AND p.business_id = r.business_id
               AND p.gateway = 'onvo' AND r.attempted_at IS NOT NULL;
            ALTER TABLE payments FORCE ROW LEVEL SECURITY;
            ALTER TABLE refunds FORCE ROW LEVEL SECURITY;
            ALTER TABLE refunds
                ADD CONSTRAINT refunds_charged_amount_check CHECK (charged_amount > 0),
                ADD CONSTRAINT refunds_attempted_at_check CHECK (attempted_at IS NULL OR charged_amount IS NOT NULL);
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE refunds
                DROP CONSTRAINT refunds_attempted_at_check,
                DROP CONSTRAINT refunds_charged_amount_check,
                DROP COLUMN charged_amount;
            SQL);
    }
};

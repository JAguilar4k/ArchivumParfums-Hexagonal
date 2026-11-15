<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A payment received on delivery (#98) is not charged online, so it has no online method: payments.method stays
     * empty for the manual gateway, whose method is in manual_method, and is still card or sinpe_mobile for the
     * others. A manual payment left by rolling this migration back loses the card it took then. The row policy shows
     * the owner of the table no business, so it is lifted while those rows change.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE payments ALTER COLUMN method DROP NOT NULL, DROP CONSTRAINT payments_method_check;
            ALTER TABLE payments NO FORCE ROW LEVEL SECURITY;
            UPDATE payments SET method = NULL WHERE gateway = 'manual';
            ALTER TABLE payments FORCE ROW LEVEL SECURITY;
            ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (
                (gateway = 'manual') = (method IS NULL) AND (method IS NULL OR method IN ('card', 'sinpe_mobile'))
            );
            SQL);
    }

    /** Give every payment an online method again: a payment received on delivery takes card, the default it had before. */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE payments DROP CONSTRAINT payments_method_check;
            ALTER TABLE payments NO FORCE ROW LEVEL SECURITY;
            UPDATE payments SET method = 'card' WHERE method IS NULL;
            ALTER TABLE payments FORCE ROW LEVEL SECURITY;
            ALTER TABLE payments
                ADD CONSTRAINT payments_method_check CHECK (method IN ('card', 'sinpe_mobile')),
                ALTER COLUMN method SET NOT NULL;
            SQL);
    }
};

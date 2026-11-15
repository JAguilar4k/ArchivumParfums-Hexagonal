<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A payment pays exactly one order or one decant pack (#143), each through its composite key with the business of
     * the payment, and a pack, like an order, has at most one pending attempt for each purpose.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->uuid('decant_pack_id')->nullable();
            $table->foreign(['decant_pack_id', 'business_id'])->references(['id', 'business_id'])->on('decant_packs');
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE payments ALTER COLUMN order_id DROP NOT NULL;
            ALTER TABLE payments ADD CONSTRAINT payments_payable_check CHECK ((order_id IS NULL) <> (decant_pack_id IS NULL));
            CREATE UNIQUE INDEX payments_one_pending_pack_purpose ON payments (business_id, decant_pack_id, purpose) WHERE status = 'pending';
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP INDEX payments_one_pending_pack_purpose;
            ALTER TABLE payments DROP CONSTRAINT payments_payable_check;
            ALTER TABLE payments ALTER COLUMN order_id SET NOT NULL;
            SQL);

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['decant_pack_id', 'business_id']);
            $table->dropColumn('decant_pack_id');
        });
    }
};

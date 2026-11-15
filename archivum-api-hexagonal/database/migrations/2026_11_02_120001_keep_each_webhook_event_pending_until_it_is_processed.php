<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A verified notice is kept before it is processed (#95): it stays pending until the queue applies it, and then it
     * is processed or failed. The key (gateway, event_id) already keeps only the first arrival of each event.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE webhook_events DROP CONSTRAINT webhook_events_status_check;
            ALTER TABLE webhook_events
                ADD CONSTRAINT webhook_events_status_check CHECK (status IN ('pending', 'processed', 'failed')),
                ALTER COLUMN status SET DEFAULT 'pending';
            SQL);
    }

    /** A notice still pending is left failed, with the key that says it was never processed. */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            UPDATE webhook_events SET status = 'failed', error = 'payments.event_not_processed' WHERE status = 'pending';
            ALTER TABLE webhook_events
                ALTER COLUMN status DROP DEFAULT,
                DROP CONSTRAINT webhook_events_status_check;
            ALTER TABLE webhook_events ADD CONSTRAINT webhook_events_status_check CHECK (status IN ('processed', 'failed'));
            SQL);
    }
};

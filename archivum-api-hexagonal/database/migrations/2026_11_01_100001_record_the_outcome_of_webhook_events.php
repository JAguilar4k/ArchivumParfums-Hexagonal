<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep the outcome of each verified notice next to it, as section 6 of the specification lists for
     * webhook_events: processed, with the moment it was applied, or failed, with the translation key of its error.
     * Nothing has written the table before #94, so the new columns need no value for existing rows.
     */
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table): void {
            $table->string('status', 16);
            $table->text('error')->nullable();
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE webhook_events
                ADD CONSTRAINT webhook_events_status_check CHECK (status IN ('processed', 'failed')),
                ADD CONSTRAINT webhook_events_error_check CHECK ((status = 'failed') = (error IS NOT NULL)),
                ADD CONSTRAINT webhook_events_processed_at_check CHECK ((status = 'processed') = (processed_at IS NOT NULL));
            SQL);
    }

    /** Remove the outcome and its constraints. */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE webhook_events
                DROP CONSTRAINT webhook_events_processed_at_check,
                DROP CONSTRAINT webhook_events_error_check,
                DROP CONSTRAINT webhook_events_status_check;
            SQL);

        Schema::table('webhook_events', function (Blueprint $table): void {
            $table->dropColumn(['status', 'error']);
        });
    }
};

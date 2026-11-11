<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the working hours of each person of the staff, one row per tramo of a weekday.
     * The composite foreign key refuses a member of another business and the exclusion constraint refuses two
     * overlapping tramos of the same person on the same weekday; the row-level policy lives in its own migration.
     */
    public function up(): void
    {
        Schema::create('staff_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->uuid('business_id');
            $table->uuid('member_id');
            $table->integer('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestampsTz();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            // A person of another business can never be scheduled in this one.
            $table->foreign(['member_id', 'business_id'])
                ->references(['id', 'business_id'])
                ->on('members')
                ->cascadeOnDelete();
            // The availability of #61 reads the tramos of one person on one weekday.
            $table->index(['business_id', 'member_id', 'weekday']);
        });

        DB::statement('ALTER TABLE staff_schedules ADD CONSTRAINT staff_schedules_weekday_check CHECK (weekday BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE staff_schedules ADD CONSTRAINT staff_schedules_starts_at_check CHECK (ends_at > starts_at)');

        DB::statement(
            'ALTER TABLE staff_schedules ADD CONSTRAINT staff_schedules_no_overlap_excl
             EXCLUDE USING gist (business_id WITH =, member_id WITH =, weekday WITH =, timerange(starts_at, ends_at) WITH &&)'
        );
    }

    /** Drop the working hours of the staff with their exclusion constraint and indexes. */
    public function down(): void
    {
        Schema::dropIfExists('staff_schedules');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('staff_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();

            // Composite foreign key required by #52
            $table->uuid('member_id');
            $table->foreign(['member_id', 'business_id'])->references(['id', 'business_id'])->on('members')->cascadeOnDelete();

            $table->smallInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE staff_schedules ADD CONSTRAINT staff_schedules_weekday_check CHECK (weekday BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE staff_schedules ADD CONSTRAINT staff_schedules_time_check CHECK (ends_at > starts_at)');

        DB::statement('
            ALTER TABLE staff_schedules 
            ADD CONSTRAINT staff_schedules_no_overlap_excl 
            EXCLUDE USING gist (
                business_id WITH =,
                member_id WITH =,
                weekday WITH =,
                timerange(starts_at, ends_at) WITH &&
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_schedules');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the blocks of the agenda: a range of instants taken out of the availability of one person, or of the
     * whole business when member_id is null. The source tells the blocks written in the panel from the ones that
     * Google Calendar will bring (#183), and the row-level policy lives in its own migration.
     */
    public function up(): void
    {
        Schema::create('schedule_blocks', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->uuid('business_id');
            $table->uuid('member_id')->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('reason', 255);
            $table->string('source', 10)->default('manual');
            $table->string('external_id', 255)->nullable();
            $table->timestampsTz();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            // A person of another business can never be blocked in this one; a null member blocks the whole business.
            $table->foreign(['member_id', 'business_id'])
                ->references(['id', 'business_id'])
                ->on('members')
                ->cascadeOnDelete();
            // The availability of #61 asks for the blocks that cross one range.
            $table->index(['business_id', 'starts_at', 'ends_at']);
        });

        DB::statement('ALTER TABLE schedule_blocks ADD CONSTRAINT schedule_blocks_starts_at_check CHECK (ends_at > starts_at)');
        DB::statement("ALTER TABLE schedule_blocks ADD CONSTRAINT schedule_blocks_source_check CHECK (source IN ('manual', 'google'))");
    }

    /** Drop the blocks of the agenda with their constraints and indexes. */
    public function down(): void
    {
        Schema::dropIfExists('schedule_blocks');
    }
};

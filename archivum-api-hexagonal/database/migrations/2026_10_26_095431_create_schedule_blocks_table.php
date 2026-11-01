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
        Schema::create('schedule_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();

            $table->uuid('member_id')->nullable();
            $table->foreign(['member_id', 'business_id'])->references(['id', 'business_id'])->on('members')->cascadeOnDelete();

            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('reason');
            $table->string('source')->default('manual');
            $table->string('external_id')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE schedule_blocks ADD CONSTRAINT schedule_blocks_time_check CHECK (ends_at > starts_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_blocks');
    }
};

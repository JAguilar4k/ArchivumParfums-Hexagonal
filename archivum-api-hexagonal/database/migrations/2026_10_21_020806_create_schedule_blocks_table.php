<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            // $table->uuid('id')->primary();
            $table->uuid('business_id');

            $table->uuid('member_id')->nullable();

            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');

            $table->string('reason');

            $table->string('source')->default('manual');
            $table->string('external_id')->nullable();

            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();

            $table->foreign(['member_id', 'business_id'])
                ->references(['id', 'business_id'])
                ->on('members')
                ->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE schedule_blocks ADD CONSTRAINT schedule_blocks_ends_at_check CHECK (ends_at > starts_at)');
        DB::statement("ALTER TABLE schedule_blocks ADD CONSTRAINT schedule_blocks_source_check CHECK (source IN ('manual', 'google'))");

        DB::statement('ALTER TABLE schedule_blocks ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE schedule_blocks FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY isolate_schedule_blocks_tenant ON schedule_blocks
            FOR ALL
            USING (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)
            WITH CHECK (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS isolate_schedule_blocks_tenant ON schedule_blocks');
        Schema::dropIfExists('schedule_blocks');
    }
};

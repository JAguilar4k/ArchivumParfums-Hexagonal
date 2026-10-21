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
        Schema::create('staff_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            // $table->uuid('id')->primary(); //
            $table->uuid('business_id');
            $table->uuid('member_id');
            $table->integer('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->foreign(['member_id', 'business_id'])
                ->references(['id', 'business_id'])
                ->on('members')
                ->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE staff_schedules ADD CONSTRAINT staff_schedules_weekday_check CHECK (weekday >= 1 AND weekday <= 7)');
        DB::statement('ALTER TABLE staff_schedules ADD CONSTRAINT staff_schedules_ends_at_check CHECK (ends_at > starts_at)');

        DB::statement('
            ALTER TABLE staff_schedules 
            ADD CONSTRAINT staff_schedules_overlap_exclude 
            EXCLUDE USING gist (
                member_id WITH =, 
                weekday WITH =, 
                timerange(starts_at, ends_at) WITH &&
            )
        ');

        DB::statement('ALTER TABLE staff_schedules ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE staff_schedules FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY isolate_staff_schedules_tenant ON staff_schedules
            FOR ALL
            USING (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)
            WITH CHECK (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS isolate_staff_schedules_tenant ON staff_schedules');
        Schema::dropIfExists('staff_schedules');
    }
};

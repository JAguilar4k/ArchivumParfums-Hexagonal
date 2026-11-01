<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['services', 'business_hours', 'staff_schedules', 'schedule_blocks'];
        foreach ($tables as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY;");
            DB::statement("
                CREATE POLICY {$table}_business_isolation ON {$table}
                USING (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)
                WITH CHECK (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid);
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

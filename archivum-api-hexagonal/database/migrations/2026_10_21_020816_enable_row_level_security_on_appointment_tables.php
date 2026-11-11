<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Appointment tables whose rows belong to one business.
     *
     * @var list<string>
     */
    private const array TABLES = ['appointments', 'appointment_perfumes'];

    /** Limit every role, the table owner included, to the rows of the business pinned in the transaction. */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement(<<<SQL
                CREATE POLICY {$table}_business_isolation ON {$table}
                    USING (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)
                    WITH CHECK (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)
                SQL);
        }
    }

    /** Drop the policies and turn row-level security off again, as the scheduling migration does. */
    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_business_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};

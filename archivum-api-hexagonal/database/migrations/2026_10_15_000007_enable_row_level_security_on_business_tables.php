<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tables whose rows belong to one business; users and businesses have no business_id.
     *
     * @var list<string>
     */
    private const array TABLES = ['members', 'customers'];

    /**
     * Tables the definer functions read before any business is pinned: the schema owner, who runs them, reads every row.
     *
     * @var list<string>
     */
    private const array READ_BY_THE_DEFINER_FUNCTIONS = ['members', 'customers'];

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

        $owner = $this->ownerRole();

        foreach (self::READ_BY_THE_DEFINER_FUNCTIONS as $table) {
            DB::statement("CREATE POLICY {$table}_definer_read ON {$table} FOR SELECT TO {$owner} USING (true)");
        }
    }

    /** Drop the policies and turn row-level security off again. */
    public function down(): void
    {
        foreach (self::READ_BY_THE_DEFINER_FUNCTIONS as $table) {
            DB::statement("DROP POLICY {$table}_definer_read ON {$table}");
        }

        foreach (self::TABLES as $table) {
            DB::statement("DROP POLICY {$table}_business_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }

    /** Quoted name of the role that owns the schema and runs the migrations. */
    private function ownerRole(): string
    {
        $name = config('database.connections.pgsql_admin.username');

        return '"'.str_replace('"', '""', is_string($name) ? $name : '').'"';
    }
};

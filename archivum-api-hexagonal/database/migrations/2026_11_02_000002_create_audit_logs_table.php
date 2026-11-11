<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the log that nobody can change. It has no foreign keys on purpose: an entry outlives the account and the
     * business it names, and a TRUNCATE ... CASCADE or a deleted row elsewhere must never reach it.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->uuid('business_id')->index();
            $table->uuid('actor_user_id')->nullable();
            $table->string('action', 60);
            $table->string('resource_type', 40);
            $table->uuid('resource_id');
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->uuid('request_id')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('now()'));
        });

        DB::statement('ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE audit_logs FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY audit_logs_business_isolation ON audit_logs
                USING (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)
                WITH CHECK (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)
            SQL);

        $name = config('database.connections.pgsql.username');
        $role = '"'.str_replace('"', '""', is_string($name) ? $name : '').'"';
        DB::statement("REVOKE UPDATE, DELETE, TRUNCATE ON audit_logs FROM {$role}");

        // migrate:fresh drops the tables but not the functions.
        DB::statement('DROP FUNCTION IF EXISTS fn_audit_logs_immutable()');
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION fn_audit_logs_immutable()
                RETURNS trigger
                LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs cannot be changed: % refused', TG_OP
                    USING ERRCODE = 'integrity_constraint_violation';
            END;
            $$;

            CREATE TRIGGER trg_audit_logs_immutable
                BEFORE UPDATE OR DELETE OR TRUNCATE ON audit_logs
                FOR EACH STATEMENT
                EXECUTE FUNCTION fn_audit_logs_immutable();
            SQL);
    }

    /** Drop the table, which takes its trigger and its policy with it, and the function. */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        DB::statement('DROP FUNCTION IF EXISTS fn_audit_logs_immutable()');
    }
};

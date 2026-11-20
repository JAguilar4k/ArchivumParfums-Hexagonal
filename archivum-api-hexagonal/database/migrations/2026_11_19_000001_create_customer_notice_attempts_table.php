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
        Schema::create('customer_notice_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->string('notice_type', 40);
            $table->uuid('reference_id');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['business_id', 'notice_type', 'reference_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE customer_notice_attempts
                ADD CONSTRAINT customer_notice_attempts_notice_type_check
                    CHECK (notice_type IN ('order_placed', 'appointment_booked'))
            SQL);
        DB::statement('ALTER TABLE customer_notice_attempts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE customer_notice_attempts FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY customer_notice_attempts_business_isolation ON customer_notice_attempts
                USING (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)
                WITH CHECK (business_id = NULLIF(current_setting('app.business_id', true), '')::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_notice_attempts');
    }
};

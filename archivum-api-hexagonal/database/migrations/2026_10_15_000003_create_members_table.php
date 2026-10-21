<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create the staff of each business: one business per person, as owner or staff. */
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->boolean('attends_appointments')->default(false);
            $table->timestampsTz();

            $table->unique(['id', 'business_id']);
        });

        DB::statement("ALTER TABLE members ADD CONSTRAINT members_role_check CHECK (role IN ('owner', 'staff'))");
    }

    /** Drop the staff table. */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
